<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Disease;
use App\Models\Indicator;
use App\Models\IngestEvent;
use App\Models\Person;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsSurveillanceData;
use Tests\TestCase;

class CaseAccessAndIngestTest extends TestCase
{
    use BuildsSurveillanceData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['surveillance.synthetic_data' => true]);
        $this->buildWorld(20);
    }

    private function payload(array $over = []): array
    {
        $today = CarbonImmutable::today();

        return $over + ['disease' => 'measles', 'facility_code' => $this->facA1->code, 'sex' => 'F', 'age_years' => 6,
            'onset_date' => $today->subDays(2)->toDateString(), 'notification_date' => $today->toDateString(), 'classification' => 'suspected', 'hospitalised' => false];
    }

    private function token(?\App\Models\OrgUnit $unit = null): string
    {
        [, $token] = ApiClient::issue('Test system', $unit ?? $this->districtA);

        return $token;
    }

    private function countFor($unit, string $disease = 'measles'): float
    {
        return (float) DB::table('indicator_values')->where(['org_unit_id' => $unit->id, 'indicator_id' => Indicator::idFor(Indicator::CASES), 'dimension_id' => Disease::where('code', $disease)->value('id'),
            'period_start' => CarbonImmutable::today()->startOfWeek()->toDateString()])->value('value');
    }

    public function test_notification_creates_a_case_and_updates_every_level_of_the_hierarchy(): void
    {
        $res = $this->withToken($this->token())->withHeaders(['Idempotency-Key' => 'k-1'])->postJson('/api/v1/notifications', $this->payload());
        $res->assertCreated()->assertJsonPath('status', 'applied');

        $this->assertSame(1, CaseRecord::count());
        foreach ([$this->facA1, $this->districtA, $this->region, $this->country] as $unit) {
            $this->assertSame(1.0, $this->countFor($unit), "{$unit->type} not updated");
        }
        $this->assertSame(0.0, $this->countFor($this->districtB));
        $this->assertSame('applied', IngestEvent::first()->status);
        $this->assertStringNotContainsString('measles', IngestEvent::first()->payload, 'payload is stored encrypted');
    }

    public function test_replaying_the_same_key_creates_nothing_new(): void
    {
        $t = $this->token();
        $this->withToken($t)->withHeaders(['Idempotency-Key' => 'same'])->postJson('/api/v1/notifications', $this->payload())->assertCreated();
        $this->withToken($t)->withHeaders(['Idempotency-Key' => 'same'])->postJson('/api/v1/notifications', $this->payload())->assertOk()->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, CaseRecord::count());
        $this->assertSame(1, Person::count());
        $this->assertSame(1.0, $this->countFor($this->country));
        $this->withToken($t)->withHeaders(['Idempotency-Key' => 'same'])->postJson('/api/v1/notifications', $this->payload(['age_years' => 40]))->assertStatus(409);
    }

    public function test_invalid_notifications_are_rejected_with_reasons_and_stored_as_rejected(): void
    {
        $t = $this->token();
        $h = ['Idempotency-Key' => 'bad-1'];
        $this->withToken($t)->postJson('/api/v1/notifications', $this->payload())->assertStatus(400);
        $this->withToken($t)->withHeaders($h)->postJson('/api/v1/notifications', $this->payload(['disease' => 'malaria']))->assertStatus(422)->assertJsonValidationErrorFor('disease', 'errors');
        $this->withToken($t)->withHeaders(['Idempotency-Key' => 'bad-2'])->postJson('/api/v1/notifications', $this->payload(['facility_code' => $this->facB1->code]))->assertStatus(422);
        $this->withToken($t)->withHeaders(['Idempotency-Key' => 'bad-3'])->postJson('/api/v1/notifications', $this->payload(['onset_date' => CarbonImmutable::today()->addDay()->toDateString()]))->assertStatus(422);
        $this->withToken($t)->withHeaders(['Idempotency-Key' => 'bad-4'])->postJson('/api/v1/notifications', $this->payload(['sex' => 'X']))->assertStatus(422);

        $this->assertSame(0, CaseRecord::count());
        $this->assertSame(4, IngestEvent::where('status', 'rejected')->count());
    }

    public function test_an_invalid_or_missing_token_is_refused(): void
    {
        $this->withHeaders(['Idempotency-Key' => 'x'])->postJson('/api/v1/notifications', $this->payload())->assertStatus(401);
        $this->withToken('ow_wrong')->withHeaders(['Idempotency-Key' => 'x'])->postJson('/api/v1/notifications', $this->payload())->assertStatus(401);
    }

    public function test_a_fhir_bundle_is_mapped_and_ingested(): void
    {
        $today = CarbonImmutable::today()->toDateString();
        $bundle = ['resourceType' => 'Bundle', 'type' => 'collection', 'entry' => [
            ['resource' => ['resourceType' => 'Patient', 'gender' => 'male', 'birthDate' => '2019-05-01']],
            ['resource' => ['resourceType' => 'Condition', 'code' => ['coding' => [['system' => 'http://hl7.org/fhir/sid/icd-10', 'code' => 'B05.9']]], 'onsetDateTime' => $today.'T08:00:00Z', 'recordedDate' => $today, 'verificationStatus' => ['coding' => [['code' => 'confirmed']]]]],
            ['resource' => ['resourceType' => 'Encounter', 'class' => ['code' => 'IMP'], 'serviceProvider' => ['identifier' => ['value' => $this->facA1->code]]]],
        ]];
        $this->withToken($this->token())->withHeaders(['Idempotency-Key' => 'fhir-1'])->postJson('/api/v1/notifications', $bundle)->assertCreated();

        $case = CaseRecord::first();
        $this->assertSame('confirmed', $case->classification);
        $this->assertTrue($case->hospitalised);
        $this->assertSame('B05', $case->icd10_code);
        $this->assertSame(2019, $case->person->birth_year);
        $this->assertSame('M', $case->person->sex);
    }

    public function test_discarding_a_case_removes_it_from_the_counts(): void
    {
        $this->withToken($this->token())->withHeaders(['Idempotency-Key' => 'd-1'])->postJson('/api/v1/notifications', $this->payload());
        $this->assertSame(1.0, $this->countFor($this->country));
        $officer = $this->userFor('district_officer', $this->districtA);
        $case = CaseRecord::first();

        $this->actingAs($officer)->put("/cases/{$case->id}", ['classification' => 'discarded', 'outcome' => 'unknown', 'status' => 'closed'])->assertRedirect();
        $this->assertSame(0.0, $this->countFor($this->country));
        $this->assertSame(0.0, $this->countFor($this->districtA));
    }

    public function test_district_officer_sees_full_detail_and_every_view_is_audited(): void
    {
        $this->withToken($this->token())->withHeaders(['Idempotency-Key' => 'v-1'])->postJson('/api/v1/notifications', $this->payload());
        $case = CaseRecord::first();
        $officer = $this->userFor('district_officer', $this->districtA);

        $list = $this->actingAs($officer)->get('/cases')->assertOk()->assertSee('Facility facA1')->assertSee('Synthetic records');
        $this->assertStringContainsString('cannot be exported', $list->getContent());
        $this->actingAs($officer)->get("/cases/{$case->id}")->assertOk()->assertSee('Facility facA1')->assertSee($case->person->pseudonym);

        $this->assertSame(1, AuditLog::where(['action' => 'case_list_viewed', 'user_id' => $officer->id])->count());
        $this->assertSame(1, AuditLog::where(['action' => 'case_viewed', 'user_id' => $officer->id, 'subject_id' => $case->id])->count());
    }

    public function test_epidemiologist_sees_pseudonymised_detail_without_facility_or_exact_dates(): void
    {
        $this->withToken($this->token())->withHeaders(['Idempotency-Key' => 'e-1'])->postJson('/api/v1/notifications', $this->payload(['age_years' => 6]));
        $case = CaseRecord::first();
        $epi = $this->userFor('epidemiologist', $this->country);

        $page = $this->actingAs($epi)->get("/cases/{$case->id}")->assertOk();
        $body = $page->getContent();
        $this->assertStringNotContainsString('Facility facA1', $body);
        $this->assertStringNotContainsString($case->onset_date->format('d M Y'), $body, 'exact onset date must not be shown');
        $this->assertStringNotContainsString(':00</small>', $body, 'event times must not be shown');
        $this->assertStringContainsString('Age group', $body);
        $this->assertStringContainsString('5-14 years', $body);
        $this->actingAs($epi)->get('/cases')->assertOk()->assertSee('Pseudonymised')->assertDontSee('Facility facA1');
        $this->actingAs($epi)->get('/cases/create')->assertForbidden();
        $this->actingAs($epi)->put("/cases/{$case->id}", ['classification' => 'confirmed', 'outcome' => 'unknown', 'status' => 'closed'])->assertForbidden();
    }

    public function test_cases_in_other_districts_are_not_reachable(): void
    {
        $this->withToken($this->token())->withHeaders(['Idempotency-Key' => 'o-1'])->postJson('/api/v1/notifications', $this->payload());
        $case = CaseRecord::first();
        $other = $this->userFor('district_officer', $this->districtB);

        $this->actingAs($other)->get("/cases/{$case->id}")->assertForbidden();
        $this->actingAs($other)->get('/cases')->assertOk()->assertDontSee($case->person->pseudonym);
        $this->actingAs($other)->put("/cases/{$case->id}", ['classification' => 'confirmed', 'outcome' => 'unknown', 'status' => 'closed'])->assertForbidden();
    }

    public function test_clinician_notifies_a_case_from_the_form_and_counts_update(): void
    {
        $clinician = $this->userFor('clinician', $this->facA1);
        $this->actingAs($clinician)->get('/cases/create')->assertOk()->assertSee('Do not enter names');
        $today = CarbonImmutable::today()->toDateString();
        $form = ['disease' => 'cholera', 'facility_code' => $this->facA1->code, 'sex' => 'M', 'age_years' => 30, 'onset_date' => $today, 'notification_date' => $today, 'classification' => 'suspected'];

        $this->actingAs($clinician)->post('/cases', $form)->assertRedirect();
        $this->assertSame(1, CaseRecord::count());
        $this->assertSame(1.0, $this->countFor($this->country, 'cholera'));
        $this->actingAs($clinician)->post('/cases', array_merge($form, ['facility_code' => $this->facB1->code]))->assertForbidden();
        $this->actingAs($clinician)->post('/cases', array_merge($form, ['age_years' => 200]))->assertSessionHasErrors('age_years');
    }

    public function test_lab_officer_sees_a_minimal_worklist_and_a_positive_result_confirms_the_case(): void
    {
        $this->withToken($this->token())->withHeaders(['Idempotency-Key' => 'l-1'])->postJson('/api/v1/notifications', $this->payload());
        $case = CaseRecord::first();
        $officer = $this->userFor('district_officer', $this->districtA);
        $this->actingAs($officer)->post("/cases/{$case->id}/lab", ['specimen_type' => 'serum', 'test_code' => 'MEASLES-IGM', 'collected_at' => now()->toDateString()])->assertRedirect();

        $lab = $this->userFor('lab_officer', $this->districtA);
        $this->actingAs($lab)->get('/lab')->assertOk()->assertSee('MEASLES-IGM')->assertDontSee('Facility facA1');
        $l = $case->labResults()->first();
        $this->actingAs($lab)->post("/lab/{$l->id}/result", ['result' => 'positive', 'resulted_at' => now()->toDateString()])->assertRedirect();

        $this->assertSame('confirmed', $case->fresh()->classification);
        $this->actingAs($lab)->get("/cases/{$case->id}")->assertForbidden();
    }
}
