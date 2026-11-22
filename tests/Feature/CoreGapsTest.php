<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Annotation;
use App\Models\ApiClient;
use App\Models\DataQualityFlag;
use App\Models\SavedView;
use App\Services\AlertEngine;
use App\Services\CaseAnalytics;
use App\Services\DataQualityChecker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsSurveillanceData;
use Tests\TestCase;

class CoreGapsTest extends TestCase
{
    use BuildsSurveillanceData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld(20);
    }

    private function notify($facility, string $disease = 'measles', array $over = []): void
    {
        [, $token] = ApiClient::issue('Test', $facility->parent);
        $t = CarbonImmutable::today();
        $this->withToken($token)->withHeaders(['Idempotency-Key' => 'k-'.uniqid()])->postJson('/api/v1/notifications', $over + [
            'disease' => $disease, 'facility_code' => $facility->code, 'sex' => 'F', 'age_years' => 6, 'onset_date' => $t->subDays(2)->toDateString(),
            'notification_date' => $t->toDateString(), 'classification' => 'confirmed', 'hospitalised' => false,
        ])->assertCreated();
    }

    public function test_case_analytics_is_scoped_and_shows_no_identifiers(): void
    {
        $this->notify($this->facA1);
        $this->notify($this->facA1, 'measles', ['sex' => 'M', 'age_years' => 30]);
        $this->notify($this->facB1);

        $officer = $this->userFor('district_officer', $this->districtA);
        $res = $this->actingAs($officer)->get(route('cases.analytics'))->assertOk()->assertSee('Epidemic curve');
        $this->assertStringNotContainsString('pseudonym', $res->getContent());
        $this->assertSame(2, app(CaseAnalytics::class)->totals(app(CaseAnalytics::class)->query($officer, $this->districtA, null, CarbonImmutable::today()->subWeeks(4)->startOfWeek(), CarbonImmutable::today()))['total']);
        $this->actingAs($officer)->get(route('cases.analytics', ['area' => $this->districtB->id]))->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'case_analytics_viewed', 'user_id' => $officer->id]);

        $this->actingAs($this->userFor('lab_officer', $this->districtA))->get(route('cases.analytics'))->assertForbidden();
    }

    public function test_fatality_interval_widens_for_small_numbers(): void
    {
        [$lowSmall, $highSmall] = CaseAnalytics::wilson(1, 4);
        [$lowBig, $highBig] = CaseAnalytics::wilson(250, 1000);
        $this->assertGreaterThan($highBig - $lowBig, $highSmall - $lowSmall);
        $this->assertSame([null, null], CaseAnalytics::wilson(0, 0));
    }

    public function test_quality_checker_flags_missing_reports_and_closes_them_when_fixed(): void
    {
        DB::table('reporting_statuses')->where('org_unit_id', $this->facA1->id)->where('week_start', '>=', $this->lastWeek->subWeeks(2)->toDateString())->update(['received' => false, 'on_time' => false]);
        $checker = app(DataQualityChecker::class);
        $checker->run();
        $flag = DataQualityFlag::where(['org_unit_id' => $this->facA1->id, 'type' => 'missing_reports'])->first();
        $this->assertNotNull($flag);
        $this->assertSame(1, DataQualityFlag::where('type', 'missing_reports')->count());

        $checker->run();
        $this->assertSame(1, DataQualityFlag::where('type', 'missing_reports')->count(), 'running twice does not duplicate');

        DB::table('reporting_statuses')->where('org_unit_id', $this->facA1->id)->update(['received' => true, 'on_time' => true]);
        $checker->run();
        $this->assertNotNull($flag->fresh()->resolved_at);
    }

    public function test_flags_are_scoped_and_only_reviewers_can_review(): void
    {
        DataQualityFlag::create(['flag_key' => 'a', 'org_unit_id' => $this->facA1->id, 'type' => 'missing_reports', 'severity' => 'warning', 'detail' => 'Alpha facility problem']);
        $b = DataQualityFlag::create(['flag_key' => 'b', 'org_unit_id' => $this->facB1->id, 'type' => 'missing_reports', 'severity' => 'warning', 'detail' => 'Bravo facility problem']);
        $officer = $this->userFor('district_officer', $this->districtA);

        $this->actingAs($officer)->get(route('quality-flags.index'))->assertOk()->assertSee('Alpha facility problem')->assertDontSee('Bravo facility problem');
        $this->actingAs($officer)->post(route('quality-flags.review', $b))->assertForbidden();
        $this->actingAs($this->userFor('clinician', $this->districtA))->post(route('quality-flags.review', $b))->assertForbidden();

        $own = DataQualityFlag::where('flag_key', 'a')->first();
        $this->actingAs($officer)->post(route('quality-flags.review', $own), ['note' => 'Phoned the facility'])->assertRedirect();
        $this->assertNotNull($own->fresh()->resolved_at);
        $this->assertSame($officer->id, $own->fresh()->resolved_by);
    }

    public function test_annotations_belong_to_their_author_and_stay_in_scope(): void
    {
        $officer = $this->userFor('district_officer', $this->districtA);
        $this->actingAs($officer)->post(route('annotations.store'), ['org_unit_id' => $this->districtB->id, 'period_start' => $this->lastWeek->toDateString(), 'text' => 'Not mine'])->assertForbidden();
        $this->actingAs($officer)->post(route('annotations.store'), ['org_unit_id' => $this->districtA->id, 'disease' => 'measles', 'period_start' => $this->lastWeek->addDays(3)->toDateString(), 'text' => 'Campaign started'])->assertRedirect();
        $note = Annotation::first();
        $this->assertSame($this->lastWeek->toDateString(), $note->period_start->toDateString(), 'moved to the start of the week');

        $this->actingAs($officer)->get(route('trends', ['disease' => 'measles']))->assertOk()->assertSee('Campaign started');
        $this->actingAs($this->userFor('district_officer', $this->districtA))->delete(route('annotations.destroy', $note))->assertForbidden();
        $this->actingAs($officer)->delete(route('annotations.destroy', $note))->assertRedirect();
        $this->assertSame(0, Annotation::count());
    }

    public function test_saved_views_are_private_and_limited_to_areas_the_user_may_see(): void
    {
        $officer = $this->userFor('district_officer', $this->districtA);
        $this->actingAs($officer)->post(route('saved-views.store'), ['name' => 'Mine', 'route' => 'trends', 'area' => $this->districtB->id])->assertForbidden();
        $this->actingAs($officer)->post(route('saved-views.store'), ['name' => 'Mine', 'route' => 'trends', 'area' => $this->districtA->id, 'disease' => 'measles', 'from' => '2026-01-05'])->assertRedirect();
        $view = SavedView::first();
        $this->assertSame(['area' => (string) $this->districtA->id, 'disease' => 'measles'], array_map('strval', $view->filters), 'dates are not kept unless asked');

        $this->actingAs($officer)->get(route('trends'))->assertSee('Mine');
        $other = $this->userFor('district_officer', $this->districtA);
        $this->actingAs($other)->get(route('trends'))->assertDontSee('>Mine<', false);
        $this->actingAs($other)->delete(route('saved-views.destroy', $view))->assertForbidden();
    }

    public function test_only_admins_manage_api_clients_and_the_token_is_not_stored(): void
    {
        $this->actingAs($this->userFor('national_manager', $this->country))->get(route('admin.api-clients'))->assertForbidden();
        $admin = $this->userFor('system_admin', $this->country);
        $res = $this->actingAs($admin)->post(route('admin.api-clients.store'), ['name' => 'Alpha HMIS', 'org_unit_id' => $this->districtA->id]);
        $res->assertRedirect(route('admin.api-clients'));
        $token = session('new_token');
        $this->assertStringStartsWith('ow_', $token);
        $client = ApiClient::where('name', 'Alpha HMIS')->first();
        $this->assertNotSame($token, $client->token_hash);
        $this->actingAs($admin)->get(route('admin.api-clients'))->assertOk()->assertSee('Alpha HMIS');

        $this->actingAs($admin)->post(route('admin.api-clients.toggle', $client));
        $this->assertNull(ApiClient::findByToken($token), 'a disabled client cannot authenticate');
    }

    public function test_threshold_and_cluster_rules_raise_alerts(): void
    {
        AlertRule::query()->update(['enabled' => false]);
        AlertRule::create(['name' => 'Threshold', 'method' => 'threshold', 'params' => ['threshold' => 30], 'severity' => 'medium', 'min_count' => 1, 'cooldown_hours' => 24, 'audience_roles' => ['district_officer'], 'enabled' => true]);
        $this->loadSeries(0, []);
        DB::table('facility_reports')->where(['org_unit_id' => $this->facA1->id, 'week_start' => $this->lastWeek->toDateString()])->update(['cases' => 35]);
        (new \App\Services\IndicatorEngine)->rebuildAll();
        $stats = app(AlertEngine::class)->run($this->lastWeek, false);
        $this->assertGreaterThan(0, $stats['created']);
        $this->assertTrue(Alert::where('org_unit_id', $this->districtA->id)->exists());
        $this->assertFalse(Alert::where('org_unit_id', $this->districtB->id)->exists());

        Alert::query()->delete();
        AlertRule::query()->update(['enabled' => false]);
        AlertRule::create(['name' => 'Cluster', 'method' => 'cluster', 'params' => ['cluster_days' => 14], 'severity' => 'medium', 'min_count' => 3, 'cooldown_hours' => 24, 'audience_roles' => ['district_officer'], 'enabled' => true]);
        foreach (range(1, 3) as $i) {
            $this->notify($this->facA1, 'cholera');
        }
        $this->notify($this->facB1, 'cholera');
        app(AlertEngine::class)->run($this->lastWeek->addWeek(), false);
        $alert = Alert::where('org_unit_id', $this->districtA->id)->first();
        $this->assertNotNull($alert);
        $this->assertEquals(3.0, $alert->observed);
        $this->assertFalse(Alert::where('org_unit_id', $this->districtB->id)->exists(), 'one case is not a cluster');
    }

    public function test_alert_rule_form_saves_each_method_with_its_own_settings(): void
    {
        $admin = $this->userFor('national_manager', $this->country);
        $this->actingAs($admin)->get(route('alert-rules.create'))->assertOk()->assertSee('When to raise an alert')->assertSee('Cluster');

        $base = ['severity' => 'auto', 'cooldown_hours' => 48, 'audience_roles' => ['district_officer'], 'enabled' => 1];
        $this->actingAs($admin)->post(route('alert-rules.store'), $base + ['name' => 'T', 'method' => 'threshold', 'min_count' => 1, 'threshold' => 25])->assertRedirect(route('alert-rules.index'));
        $this->assertSame(25, AlertRule::where('name', 'T')->first()->params['threshold']);

        $this->actingAs($admin)->post(route('alert-rules.store'), $base + ['name' => 'T2', 'method' => 'threshold', 'min_count' => 1])->assertSessionHasErrors('threshold');
        $this->actingAs($admin)->post(route('alert-rules.store'), $base + ['name' => 'C', 'method' => 'cluster', 'min_count' => 4, 'cluster_days' => 10])->assertRedirect();
        $this->assertSame(10, AlertRule::where('name', 'C')->first()->params['cluster_days']);
        $this->actingAs($admin)->post(route('alert-rules.store'), $base + ['name' => 'S', 'method' => 'single_case', 'min_count' => 9])->assertRedirect();
        $this->assertSame(1, AlertRule::where('name', 'S')->first()->min_count, 'a single-case rule always fires on one case');
    }

    public function test_user_form_explains_roles_and_creates_a_user(): void
    {
        $admin = $this->userFor('system_admin', $this->country);
        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()->assertSee('What they can see')->assertSee('Sees only the lab worklist', false);
    }

    public function test_sidebar_highlights_exactly_one_item(): void
    {
        $officer = $this->userFor('district_officer', $this->districtA);
        foreach ([route('cases.analytics') => 'Case analytics', route('cases.index') => 'Cases', route('cases.create') => 'Notify a case', route('quality-flags.index') => 'Data quality'] as $url => $label) {
            $html = $this->actingAs($officer)->get($url)->getContent();
            preg_match_all('/<li class="active">\s*<a[^>]*>.*?<span>([^<]+)<\/span>/s', $html, $m);
            $this->assertSame([$label], $m[1], "wrong highlight on $url");
        }
        $admin = $this->userFor('system_admin', $this->country);
        $html = $this->actingAs($admin)->get(route('admin.users'))->getContent();
        preg_match_all('/<li class="active">\s*<a[^>]*>.*?<span>([^<]+)<\/span>/s', $html, $m);
        $this->assertSame(['Users'], $m[1]);
    }

    public function test_super_admin_reaches_every_screen(): void
    {
        $this->notify($this->facA1);
        $u = $this->userFor('super_admin', $this->country);
        foreach (['dashboard', 'trends', 'map', 'quality', 'vaccination', 'reporting', 'forecasts', 'cases.index', 'cases.analytics', 'cases.create', 'lab.index', 'alerts.index',
            'alert-rules.index', 'admin.users', 'admin.api-clients', 'admin.sources', 'admin.audit', 'quality-flags.index', 'exports.index'] as $name) {
            $this->actingAs($u)->get(route($name))->assertOk();
        }
        $this->actingAs($u)->get(route('areas.show', $this->districtB))->assertOk();
    }

    public function test_temporary_password_must_be_changed_before_anything_else(): void
    {
        $u = $this->userFor('district_officer', $this->districtA);
        $u->update(['must_change_password' => true]);

        $this->actingAs($u)->get(route('dashboard'))->assertRedirect(route('password.edit'));
        $this->actingAs($u)->get(route('cases.index'))->assertRedirect(route('password.edit'));
        $this->actingAs($u)->get(route('password.edit'))->assertOk()->assertSee('Choose your own password');

        $this->actingAs($u)->put(route('password.update'), ['current_password' => 'wrong', 'password' => 'Better-Password-2026', 'password_confirmation' => 'Better-Password-2026'])->assertSessionHasErrors('current_password');
        $this->actingAs($u)->put(route('password.update'), ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->actingAs($u)->put(route('password.update'), ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasErrors('password');
        $this->assertTrue($u->fresh()->must_change_password);

        $this->actingAs($u)->put(route('password.update'), ['current_password' => 'password', 'password' => 'Better-Password-2026', 'password_confirmation' => 'Better-Password-2026'])->assertRedirect(route('dashboard'));
        $fresh = $u->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Better-Password-2026', $fresh->password));
        $this->actingAs($fresh)->get(route('dashboard'))->assertOk();
    }

    public function test_production_users_are_created_once_and_keep_changed_passwords(): void
    {
        \App\Models\OrgUnit::factory()->childOf($this->region, 'district')->create(['code' => 'UG3083', 'name' => 'Lira']);
        $lira = \App\Models\OrgUnit::where('code', 'UG3083')->first();
        \App\Models\OrgUnit::factory()->childOf($lira, 'facility')->create(['code' => 'F11073118391', 'name' => 'Lira Regional Referral Hospital']);
        $this->country->update(['code' => 'UG']);

        $this->seed(\Database\Seeders\ProductionUsersSeeder::class);
        $this->assertSame(7, \App\Models\User::where('email', 'like', '%@joseneous.com')->count());
        foreach (['amito', 'janet', 'susan'] as $n) {
            $u = \App\Models\User::where('email', "$n@joseneous.com")->first();
            $this->assertTrue($u->hasRole('super_admin'));
            $this->assertSame(['Uganda'], $u->orgUnits->pluck('name')->all() ?: ['Uganda'], 'country scope');
            $this->assertCount(18, $u->getAllPermissions());
            $this->assertTrue($u->must_change_password);
        }
        $this->assertSame('Lira', \App\Models\User::where('email', 'dho.lira@joseneous.com')->first()->orgUnits->first()->name);

        $amito = \App\Models\User::where('email', 'amito@joseneous.com')->first();
        $amito->forceFill(['password' => \Illuminate\Support\Facades\Hash::make('My-Own-Password-2026'), 'must_change_password' => false])->save();
        $this->seed(\Database\Seeders\ProductionUsersSeeder::class);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('My-Own-Password-2026', $amito->fresh()->password), 're-running never resets a password');
        $this->assertFalse($amito->fresh()->must_change_password);
    }
}
