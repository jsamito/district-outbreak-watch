<?php

namespace Tests\Feature;

use App\Mail\AlertDigestMail;
use App\Mail\AlertMail;
use App\Models\Alert;
use App\Models\AlertDelivery;
use App\Models\AlertRule;
use App\Models\Disease;
use App\Services\AlertDispatcher;
use App\Services\AlertEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\BuildsSurveillanceData;
use Tests\TestCase;

class AlertingTest extends TestCase
{
    use BuildsSurveillanceData, RefreshDatabase;

    private function worldWithOutbreak(int $cases = 40): void
    {
        $this->buildWorld(0, false);
        $this->loadSeries(30, $this->outbreak($this->facA1, 'measles', $cases, 1));
    }

    public function test_a_clear_rise_creates_one_alert_in_the_right_district_only(): void
    {
        $this->worldWithOutbreak();
        $stats = app(AlertEngine::class)->run($this->lastWeek, false);

        $this->assertSame(1, $stats['created']);
        $a = Alert::first();
        $this->assertSame($this->districtA->id, $a->org_unit_id);
        $this->assertSame(Disease::where('code', 'measles')->value('id'), $a->disease_id);
        $this->assertSame('high', $a->severity);
        $this->assertEquals(42.0, $a->observed, 'districtA total = 40 + 2');
        $this->assertGreaterThan(0, $a->expected);
        $this->assertSame('open', $a->status);
    }

    public function test_quiet_data_raises_no_alerts_and_small_numbers_are_ignored(): void
    {
        $this->buildWorld(30);
        $this->assertSame(0, app(AlertEngine::class)->run($this->lastWeek, false)['created']);

        // 4 cases is above the baseline but below the minimum count of 5.
        DB::table('facility_reports')->where(['org_unit_id' => $this->facA1->id, 'week_start' => $this->lastWeek->toDateString(), 'disease_id' => Disease::where('code', 'measles')->value('id')])->update(['cases' => 4]);
        (new \App\Services\IndicatorEngine)->rebuildCases();
        $this->assertSame(0, app(AlertEngine::class)->run($this->lastWeek, false)['created']);
    }

    public function test_the_same_signal_does_not_create_a_second_alert_and_ongoing_weeks_extend_the_first(): void
    {
        $this->buildWorld(0, false);
        $this->loadSeries(30, $this->outbreak($this->facA1, 'measles', 40, 2));
        $engine = app(AlertEngine::class);
        $prev = $this->lastWeek->subWeek();

        $engine->run($prev, false);
        $engine->run($prev, false);
        $this->assertSame(1, Alert::count());

        $engine->run($this->lastWeek, false);
        $this->assertSame(1, Alert::count(), 'a second signalling week extends the existing alert');
        $a = Alert::first();
        $this->assertSame(2, $a->signal_weeks);
        $this->assertSame($this->lastWeek->toDateString(), $a->last_signal_week->toDateString());
    }

    public function test_an_alert_resolves_after_two_quiet_weeks_and_reopens_within_the_cooldown(): void
    {
        $this->buildWorld(0, false);
        $this->loadSeries(30, $this->outbreak($this->facA1, 'measles', 40, 1));
        $engine = app(AlertEngine::class);
        $engine->run($this->lastWeek, false);
        $alert = Alert::first();

        // The outbreak ends: the latest week is back to baseline and the last signal was three weeks ago.
        $measles = Disease::where('code', 'measles')->value('id');
        DB::table('facility_reports')->where(['org_unit_id' => $this->facA1->id, 'disease_id' => $measles, 'week_start' => $this->lastWeek->toDateString()])->update(['cases' => 2]);
        (new \App\Services\IndicatorEngine)->rebuildCases();
        $alert->update(['last_signal_week' => $this->lastWeek->subWeeks(3)]);
        $engine->run($this->lastWeek, false);
        $this->assertSame('resolved', $alert->fresh()->status);

        // The signal returns inside the 14 day cooldown: the old alert is reopened rather than a new one created.
        DB::table('facility_reports')->where(['org_unit_id' => $this->facA1->id, 'disease_id' => $measles, 'week_start' => $this->lastWeek->toDateString()])->update(['cases' => 40]);
        (new \App\Services\IndicatorEngine)->rebuildCases();
        $alert->update(['resolved_at' => now()->subDay()]);
        $engine->run($this->lastWeek, false);
        $this->assertSame(1, Alert::count());
        $this->assertSame('open', $alert->fresh()->status);
        $this->assertNull($alert->fresh()->acknowledged_at);
    }

    public function test_a_single_case_of_an_immediately_notifiable_disease_alerts_at_once(): void
    {
        $this->buildWorld(0, false);
        $this->loadSeries(12, [$this->facB1->id => ['ebola' => [$this->lastWeek->toDateString() => 1]]]);
        $stats = app(AlertEngine::class)->run($this->lastWeek, false);

        $this->assertSame(1, $stats['created']);
        $a = Alert::first();
        $this->assertSame($this->districtB->id, $a->org_unit_id);
        $this->assertSame('high', $a->severity);
        $this->assertSame('single_case', $a->rule->method);
    }

    public function test_only_people_whose_role_and_area_match_are_notified(): void
    {
        Mail::fake();
        $this->worldWithOutbreak();
        $officerA = $this->userFor('district_officer', $this->districtA);
        $officerB = $this->userFor('district_officer', $this->districtB);
        $manager = $this->userFor('national_manager', $this->country);
        $inactive = $this->userFor('district_officer', $this->districtA);
        $inactive->update(['active' => false]);

        app(AlertEngine::class)->run($this->lastWeek, true);

        Mail::assertSent(AlertMail::class, fn ($m) => $m->hasTo($officerA->email));
        Mail::assertNotSent(AlertMail::class, fn ($m) => $m->hasTo($officerB->email) || $m->hasTo($manager->email) || $m->hasTo($inactive->email));
    }

    public function test_lower_severity_waits_for_the_daily_digest_and_people_can_opt_out(): void
    {
        Mail::fake();
        $this->buildWorld(0, false);
        $this->loadSeries(30, $this->outbreak($this->facA1, 'measles', 9, 1)); // 11 vs about 4: medium
        $digestOnly = $this->userFor('district_officer', $this->districtA);
        $off = $this->userFor('district_officer', $this->districtA);
        \App\Models\AlertPreference::create(['user_id' => $off->id, 'mode' => 'off']);

        app(AlertEngine::class)->run($this->lastWeek, true);

        $this->assertSame('medium', Alert::first()->severity);
        Mail::assertNothingSent();
        $this->assertSame(1, AlertDelivery::where(['user_id' => $digestOnly->id, 'mode' => 'digest', 'status' => 'queued'])->count());
        $this->assertSame(0, AlertDelivery::where('user_id', $off->id)->count());

        $this->assertSame(1, app(AlertDispatcher::class)->sendDigests());
        Mail::assertSent(AlertDigestMail::class, fn ($m) => $m->hasTo($digestOnly->email) && $m->alerts->count() === 1);
        $this->assertSame(0, AlertDelivery::where('status', 'queued')->count());
        $this->assertSame(0, app(AlertDispatcher::class)->sendDigests(), 'nothing left to send');
    }

    public function test_the_hourly_limit_moves_extra_immediate_emails_to_the_digest(): void
    {
        Mail::fake();
        $this->worldWithOutbreak();
        $officer = $this->userFor('district_officer', $this->districtA);
        RateLimiter::clear('alert-instant:'.$officer->id);
        for ($i = 0; $i < AlertDispatcher::MAX_INSTANT_PER_HOUR; $i++) {
            RateLimiter::hit('alert-instant:'.$officer->id, 3600);
        }

        app(AlertEngine::class)->run($this->lastWeek, true);

        Mail::assertNothingSent();
        $delivery = AlertDelivery::where('user_id', $officer->id)->first();
        $this->assertSame('digest', $delivery->mode);
        $this->assertStringContainsString('limit', $delivery->reason);
    }

    public function test_unacknowledged_alerts_escalate_to_national_managers_and_acknowledged_ones_do_not(): void
    {
        Mail::fake();
        $this->worldWithOutbreak();
        $officer = $this->userFor('district_officer', $this->districtA);
        $manager = $this->userFor('national_manager', $this->country);
        app(AlertEngine::class)->run($this->lastWeek, true);
        $alert = Alert::first();
        $alert->update(['triggered_at' => now()->subHours(13)]);

        $this->artisan('surveillance:escalate-alerts')->assertSuccessful();
        $this->assertSame('escalated', $alert->fresh()->status);
        $this->assertSame(1, $alert->fresh()->escalation_level);
        Mail::assertSent(AlertMail::class, fn ($m) => $m->hasTo($manager->email) && $m->escalated);

        $other = Alert::create(['alert_rule_id' => $alert->alert_rule_id, 'org_unit_id' => $this->districtB->id, 'disease_id' => $alert->disease_id, 'period_start' => $this->lastWeek, 'last_signal_week' => $this->lastWeek,
            'observed' => 30, 'expected' => 2, 'upper_limit' => 6, 'severity' => 'high', 'status' => 'open', 'triggered_at' => now()->subHours(20)]);
        $this->actingAs($this->userFor('district_officer', $this->districtB))->post("/alerts/{$other->id}/acknowledge", ['note' => 'Team dispatched'])->assertRedirect();
        $this->artisan('surveillance:escalate-alerts')->assertSuccessful();
        $this->assertSame('acknowledged', $other->fresh()->status);
        $this->assertSame(0, $other->fresh()->escalation_level);
    }

    public function test_acknowledging_requires_permission_and_scope_and_is_audited(): void
    {
        $this->worldWithOutbreak();
        app(AlertEngine::class)->run($this->lastWeek, false);
        $alert = Alert::first();
        $epi = $this->userFor('epidemiologist', $this->country);
        $otherOfficer = $this->userFor('district_officer', $this->districtB);
        $officer = $this->userFor('district_officer', $this->districtA);

        $this->actingAs($epi)->post("/alerts/{$alert->id}/acknowledge")->assertForbidden();
        $this->actingAs($otherOfficer)->post("/alerts/{$alert->id}/acknowledge")->assertForbidden();
        $this->actingAs($otherOfficer)->get("/alerts/{$alert->id}")->assertForbidden();
        $this->actingAs($officer)->post("/alerts/{$alert->id}/acknowledge", ['note' => str_repeat('x', 501)])->assertSessionHasErrors('note');
        $this->actingAs($officer)->post("/alerts/{$alert->id}/acknowledge", ['note' => 'Investigating'])->assertRedirect();

        $this->assertSame('acknowledged', $alert->fresh()->status);
        $this->assertSame($officer->id, $alert->fresh()->acknowledged_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'alert_acknowledged', 'user_id' => $officer->id]);
        $this->actingAs($officer)->get('/alerts')->assertOk()->assertSee('Alpha District');
    }

    public function test_only_managers_can_edit_alert_rules_and_changes_are_validated_and_audited(): void
    {
        $this->buildWorld(5);
        $manager = $this->userFor('national_manager', $this->country);
        $officer = $this->userFor('district_officer', $this->districtA);
        $this->actingAs($officer)->get('/alert-rules')->assertForbidden();

        $rule = AlertRule::first();
        $good = ['name' => 'Tighter rule', 'method' => 'outbreak_signal', 'severity' => 'auto', 'min_count' => 8, 'cooldown_hours' => 100, 'escalate_after_minutes' => 60,
            'baseline_weeks' => 10, 'sigmas' => 2.5, 'audience_roles' => ['district_officer'], 'enabled' => '1'];
        $this->actingAs($manager)->put("/alert-rules/{$rule->id}", array_merge($good, ['min_count' => 0]))->assertSessionHasErrors('min_count');
        $this->actingAs($manager)->put("/alert-rules/{$rule->id}", array_merge($good, ['audience_roles' => ['system_admin']]))->assertSessionHasErrors('audience_roles.0');
        $this->actingAs($manager)->put("/alert-rules/{$rule->id}", $good)->assertRedirect('/alert-rules');

        $r = $rule->fresh();
        $this->assertSame(8, $r->min_count);
        $this->assertSame(2.5, $r->params['sigmas']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'alert_rule_updated', 'user_id' => $manager->id]);
    }
}
