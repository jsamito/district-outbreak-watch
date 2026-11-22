<?php

namespace App\Services;

use App\Events\CaseNotified;
use App\Models\ApiClient;
use App\Models\CaseEvent;
use App\Models\CaseRecord;
use App\Models\Diagnosis;
use App\Models\Disease;
use App\Models\Encounter;
use App\Models\IngestEvent;
use App\Models\Indicator;
use App\Models\OrgUnit;
use App\Models\Person;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Single entry point for new case notifications (web form, API, simulator).
 * Each notification is stored (encrypted) under an idempotency key, validated, turned into a case,
 * folded into the weekly indicators for every area above the facility, checked against the alert
 * rules and announced to open dashboards. Replaying the same key never creates a second case.
 */
class CaseIngestService
{
    public function __construct(private IndicatorEngine $indicators, private AlertEngine $alerts) {}

    /**
     * @param array<string,mixed> $payload normalised notification
     * @param OrgUnit|null $scope the area the submitter may report for (null = no restriction check)
     * @return array{event:IngestEvent,case:?CaseRecord,duplicate:bool}
     *
     * @throws ValidationException when the payload is invalid or conflicts with an earlier use of the key
     */
    public function ingest(array $payload, string $channel, string $key, ?OrgUnit $scope = null, ?ApiClient $client = null, ?User $user = null): array
    {
        $hash = hash('sha256', json_encode($payload));
        $existing = IngestEvent::where('idempotency_key', $key)->first();
        if ($existing) {
            if ($existing->payload_hash !== $hash) {
                throw ValidationException::withMessages(['idempotency_key' => 'This key was already used with a different notification.']);
            }

            return ['event' => $existing, 'case' => CaseRecord::where('ingest_event_id', $existing->id)->first(), 'duplicate' => true];
        }

        $event = IngestEvent::create([
            'api_client_id' => $client?->id, 'channel' => $channel, 'idempotency_key' => $key, 'payload' => Crypt::encryptString(json_encode($payload)),
            'payload_hash' => $hash, 'status' => 'received', 'received_at' => now(),
        ]);

        try {
            $data = $this->validate($payload, $scope);
        } catch (ValidationException $e) {
            $event->update(['status' => 'rejected', 'error' => mb_substr(collect($e->errors())->flatten()->join('; '), 0, 500), 'processed_at' => now()]);
            throw $e;
        }

        $case = DB::transaction(function () use ($data, $event, $user) {
            $facility = $data['facility'];
            $notified = $data['notification_date'];
            $person = Person::create([
                'pseudonym' => (string) Str::uuid(), 'sex' => $data['sex'], 'birth_year' => $data['birth_year'],
                'residence_org_unit_id' => $facility->parent_id, 'is_synthetic' => (bool) config('surveillance.synthetic_data'),
            ]);
            $case = CaseRecord::create([
                'person_id' => $person->id, 'disease_id' => $data['disease']->id, 'reporting_org_unit_id' => $facility->id, 'residence_org_unit_id' => $facility->parent_id,
                'onset_date' => $data['onset_date'], 'notification_date' => $notified, 'classification' => $data['classification'], 'outcome' => $data['outcome'],
                'hospitalised' => $data['hospitalised'], 'icd10_code' => $data['disease']->icd10_code, 'status' => 'reported', 'epi_week' => $notified->startOfWeek(),
                'ingest_event_id' => $event->id, 'created_by' => $user?->id, 'is_synthetic' => (bool) config('surveillance.synthetic_data'),
            ]);
            $encounter = Encounter::create(['person_id' => $person->id, 'case_id' => $case->id, 'org_unit_id' => $facility->id, 'encountered_at' => $notified->setTime(9, 0),
                'type' => $data['hospitalised'] ? 'IPD' : 'OPD', 'notes' => $data['notes'] ?? null]);
            Diagnosis::create(['encounter_id' => $encounter->id, 'icd10_code' => $data['disease']->icd10_code, 'is_primary' => true]);
            CaseEvent::create(['case_id' => $case->id, 'type' => 'notified', 'data' => ['channel' => $event->channel], 'user_id' => $user?->id, 'occurred_at' => now()]);

            if ($data['classification'] !== 'discarded') {
                $this->foldIntoAggregates($case, $facility, (string) $case->epi_week->toDateString());
            }
            $event->update(['status' => 'applied', 'processed_at' => now()]);

            return $case;
        });

        $this->afterCommit($case);

        return ['event' => $event, 'case' => $case, 'duplicate' => false];
    }

    /** Re-count a case that changed classification or outcome. */
    public function reclassify(CaseRecord $case, string $classification, string $outcome): void
    {
        $was = $case->classification !== 'discarded';
        $now = $classification !== 'discarded';
        $week = $case->epi_week->toDateString();
        DB::transaction(function () use ($case, $classification, $outcome, $was, $now, $week) {
            if ($was !== $now) {
                $this->indicators->incrementCases($case->facility, $case->disease_id, $week, $now ? 1 : -1);
                DB::table('facility_reports')->where(['org_unit_id' => $case->reporting_org_unit_id, 'disease_id' => $case->disease_id, 'week_start' => $week])
                    ->update(['cases' => DB::raw($now ? 'cases + 1' : 'GREATEST(cases - 1, 0)')]);
            }
            $deathDelta = ($outcome === 'died' ? 1 : 0) - ($case->outcome === 'died' ? 1 : 0);
            if ($deathDelta !== 0) {
                DB::table('facility_reports')->where(['org_unit_id' => $case->reporting_org_unit_id, 'disease_id' => $case->disease_id, 'week_start' => $week])
                    ->update(['deaths' => DB::raw($deathDelta > 0 ? 'deaths + 1' : 'GREATEST(deaths - 1, 0)')]);
            }
            $case->update(['classification' => $classification, 'outcome' => $outcome]);
        });
    }

    /** @return array<string,mixed> */
    private function validate(array $p, ?OrgUnit $scope): array
    {
        $today = CarbonImmutable::today();
        $v = Validator::make($p, [
            'disease' => ['required', 'string', 'exists:diseases,code'],
            'facility_code' => ['required', 'string', 'exists:org_units,code'],
            'sex' => ['required', 'in:M,F,U'],
            'age_years' => ['nullable', 'integer', 'min:0', 'max:120', 'required_without:birth_year'],
            'birth_year' => ['nullable', 'integer', 'min:1900', 'max:'.$today->year, 'required_without:age_years'],
            'onset_date' => ['required', 'date', 'before_or_equal:today'],
            'notification_date' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:onset_date'],
            'classification' => ['nullable', 'in:suspected,probable,confirmed,discarded'],
            'outcome' => ['nullable', 'in:unknown,alive,recovered,died'],
            'hospitalised' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $v->after(function ($validator) use ($p, $scope) {
            $d = Disease::where('code', $p['disease'] ?? '')->first();
            if ($d && ! $d->case_based) {
                $validator->errors()->add('disease', 'This disease is reported in weekly aggregates, not as individual cases.');
            }
            $f = OrgUnit::where('code', $p['facility_code'] ?? '')->first();
            if ($f && $f->type !== OrgUnit::FACILITY) {
                $validator->errors()->add('facility_code', 'The code must identify a health facility.');
            } elseif ($f && $scope && ! $f->isWithin($scope)) {
                $validator->errors()->add('facility_code', 'This facility is outside the area you may report for.');
            }
        });
        $v->validate();

        $notified = isset($p['notification_date']) ? CarbonImmutable::parse($p['notification_date']) : $today;
        $birthYear = isset($p['birth_year']) ? (int) $p['birth_year'] : $notified->year - (int) $p['age_years'];

        return [
            'disease' => Disease::where('code', $p['disease'])->first(), 'facility' => OrgUnit::where('code', $p['facility_code'])->first(),
            'sex' => $p['sex'], 'birth_year' => $birthYear, 'onset_date' => CarbonImmutable::parse($p['onset_date']), 'notification_date' => $notified,
            'classification' => $p['classification'] ?? 'suspected', 'outcome' => $p['outcome'] ?? 'unknown', 'hospitalised' => (bool) ($p['hospitalised'] ?? false), 'notes' => $p['notes'] ?? null,
        ];
    }

    private function foldIntoAggregates(CaseRecord $case, OrgUnit $facility, string $week): void
    {
        DB::statement("
            INSERT INTO facility_reports (org_unit_id, disease_id, week_start, cases, deaths, origin) VALUES (?, ?, ?, 1, 0, 'reported')
            ON DUPLICATE KEY UPDATE cases = cases + 1
        ", [$facility->id, $case->disease_id, $week]);

        $status = DB::table('reporting_statuses')->where(['org_unit_id' => $facility->id, 'week_start' => $week])->first();
        if (! $status) {
            DB::table('reporting_statuses')->insert(['org_unit_id' => $facility->id, 'week_start' => $week, 'received' => true, 'on_time' => true, 'received_at' => now()]);
        } elseif (! $status->received) {
            DB::table('reporting_statuses')->where('id', $status->id)->update(['received' => true, 'on_time' => CarbonImmutable::parse($week)->addDays(9)->endOfDay()->isFuture(), 'received_at' => now()]);
            $this->indicators->markReported($facility, $week);
        }
        $this->indicators->incrementCases($facility, $case->disease_id, $week, 1);
    }

    private function afterCommit(CaseRecord $case): void
    {
        try {
            $district = $case->facility->parent;
            $week = CarbonImmutable::parse($case->epi_week);
            $this->alerts->run($week, true, [$district->id]);
            $total = (float) DB::table('indicator_values')->where(['org_unit_id' => $district->id, 'indicator_id' => Indicator::idFor(Indicator::CASES), 'dimension_id' => $case->disease_id, 'period_type' => 'week', 'period_start' => $week->toDateString()])->value('value');
            $ids = array_filter([$district->id, $district->parent_id, $district->parent?->parent_id]);
            event(new CaseNotified(array_values($ids), $case->disease->name, $district->name, $week->toDateString(), (int) $total));
        } catch (\Throwable $e) {
            Log::warning('Post-notification processing failed: '.$e->getMessage());
        }
    }
}
