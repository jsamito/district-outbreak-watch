<?php

namespace App\Services;

use App\Models\Antigen;
use App\Models\DataQualityFlag;
use App\Models\Indicator;
use App\Models\OrgUnit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Looks for problems in the data itself and records them as flags that people can review.
 * Running it again is safe: a problem that is still there keeps its flag, one that went away is closed by the system,
 * and one a person marked as reviewed stays reviewed.
 */
class DataQualityChecker
{
    public function __construct(private AggregateQuery $query) {}

    /** @return array{found:int,created:int,reopened:int,closed:int,open:int} */
    public function run(): array
    {
        $week = $this->query->latestWeek();
        $found = [];
        foreach ([$this->missingReports($week), $this->lateReporting($week), $this->coverageOver100($week), $this->missingPopulation($week),
            $this->implausibleJump($week), $this->duplicateCases()] as $batch) {
            foreach ($batch as $row) {
                $found[$row['flag_key']] = $row;
            }
        }

        $stats = ['found' => count($found), 'created' => 0, 'reopened' => 0, 'closed' => 0, 'open' => 0];
        $existing = DataQualityFlag::all()->keyBy('flag_key');
        $new = [];
        foreach ($found as $key => $row) {
            $flag = $existing[$key] ?? null;
            if (! $flag) {
                $new[] = $row + ['created_at' => now(), 'updated_at' => now()];
            } elseif ($flag->resolved_at && ! $flag->resolved_by) {
                $flag->update(['resolved_at' => null, 'detail' => $row['detail'], 'severity' => $row['severity']]);
                $stats['reopened']++;
            } elseif (! $flag->resolved_at && $flag->detail !== $row['detail']) {
                $flag->update(['detail' => $row['detail'], 'severity' => $row['severity']]);
            }
        }
        foreach (array_chunk($new, 500) as $chunk) {
            DB::table('data_quality_flags')->insert($chunk);
        }
        $stats['created'] = count($new);

        foreach ($existing as $key => $flag) {
            if (! isset($found[$key]) && ! $flag->resolved_at) {
                $flag->update(['resolved_at' => now(), 'resolution_note' => 'No longer found by the automatic check.']);
                $stats['closed']++;
            }
        }
        $stats['open'] = DataQualityFlag::open()->count();

        return $stats;
    }

    private function row(string $type, int $unit, string $severity, ?string $period, string $detail, string $discriminator = ''): array
    {
        return ['flag_key' => substr(sha1($type.'|'.$unit.'|'.$period.'|'.$discriminator), 0, 40), 'org_unit_id' => $unit, 'type' => $type, 'severity' => $severity,
            'period_start' => $period, 'detail' => mb_substr($detail, 0, 250), 'resolved_at' => null];
    }

    /** Sentinel facilities with no report in the last three complete weeks. */
    private function missingReports(?CarbonImmutable $week): array
    {
        if (! $week) {
            return [];
        }
        $from = $week->subWeeks(2)->toDateString();

        return DB::table('reporting_statuses')->join('org_units', 'org_units.id', '=', 'reporting_statuses.org_unit_id')->where('org_units.type', OrgUnit::FACILITY)
            ->whereBetween('week_start', [$from, $week->toDateString()])->groupBy('reporting_statuses.org_unit_id', 'org_units.name')
            ->havingRaw('COUNT(*) = 3 AND SUM(received) = 0')->get(['reporting_statuses.org_unit_id', 'org_units.name'])
            ->map(fn ($r) => $this->row('missing_reports', $r->org_unit_id, 'warning', $week->toDateString(), $r->name.' has sent no weekly report since '.$week->subWeeks(3)->format('d M Y').'.'))->all();
    }

    /** Facilities that mostly report after the deadline over the last eight weeks. */
    private function lateReporting(?CarbonImmutable $week): array
    {
        if (! $week) {
            return [];
        }

        return DB::table('reporting_statuses')->join('org_units', 'org_units.id', '=', 'reporting_statuses.org_unit_id')->where('org_units.type', OrgUnit::FACILITY)
            ->whereBetween('week_start', [$week->subWeeks(7)->toDateString(), $week->toDateString()])->where('received', true)
            ->groupBy('reporting_statuses.org_unit_id', 'org_units.name')->havingRaw('COUNT(*) >= 4 AND SUM(on_time) / COUNT(*) < 0.5')
            ->get(['reporting_statuses.org_unit_id', 'org_units.name', DB::raw('COUNT(*) AS received'), DB::raw('SUM(on_time) AS on_time')])
            ->map(fn ($r) => $this->row('late_reporting', $r->org_unit_id, 'info', $week->toDateString(), $r->name.': '.$r->on_time.' of '.$r->received.' reports in the last eight weeks arrived on time.'))->all();
    }

    /** District vaccination coverage above 100% in the last six months. */
    private function coverageOver100(?CarbonImmutable $week): array
    {
        $antigens = Antigen::pluck('name', 'id');
        $since = ($week ?? CarbonImmutable::today())->subMonths(6)->startOfMonth()->toDateString();

        return DB::table('indicator_values')->join('org_units', 'org_units.id', '=', 'indicator_values.org_unit_id')->where('org_units.type', OrgUnit::DISTRICT)
            ->where('indicator_id', Indicator::idFor(Indicator::VACCINATION))->where('period_type', 'month')->where('period_start', '>=', $since)->where('value', '>', 100)
            ->get(['indicator_values.org_unit_id', 'org_units.name', 'dimension_id', 'period_start', 'value'])
            ->map(fn ($r) => $this->row('coverage_over_100', $r->org_unit_id, 'warning', $r->period_start,
                ($antigens[$r->dimension_id] ?? 'Antigen').' coverage in '.$r->name.' was '.round($r->value).'% in '.CarbonImmutable::parse($r->period_start)->format('F Y').'.', (string) $r->dimension_id))->all();
    }

    /** Districts with no population figure for the latest year, which blocks rates and coverage. */
    private function missingPopulation(?CarbonImmutable $week): array
    {
        $year = ($week ?? CarbonImmutable::today())->year;

        return OrgUnit::where('type', OrgUnit::DISTRICT)->whereNotIn('id', DB::table('populations')->where('year', '>=', $year - 1)->where('year', '<=', $year)->select('org_unit_id'))
            ->get(['id', 'name'])->map(fn ($u) => $this->row('missing_population', $u->id, 'error', null, $u->name.' has no population figure for '.$year.', so rates and coverage cannot be shown.'))->all();
    }

    /** A facility count at least ten times its recent average, in the latest week. */
    private function implausibleJump(?CarbonImmutable $week): array
    {
        if (! $week) {
            return [];
        }
        $prev = DB::table('facility_reports')->whereBetween('week_start', [$week->subWeeks(8)->toDateString(), $week->subWeek()->toDateString()])
            ->groupBy('org_unit_id', 'disease_id')->selectRaw('org_unit_id, disease_id, AVG(cases) AS mean');

        return DB::table('facility_reports AS f')->joinSub($prev, 'p', fn ($j) => $j->on('p.org_unit_id', '=', 'f.org_unit_id')->on('p.disease_id', '=', 'f.disease_id'))
            ->join('org_units', 'org_units.id', '=', 'f.org_unit_id')->join('diseases', 'diseases.id', '=', 'f.disease_id')
            ->where('f.week_start', $week->toDateString())->where('f.cases', '>=', 50)->whereRaw('f.cases >= 10 * GREATEST(p.mean, 1)')
            ->get(['f.org_unit_id', 'f.disease_id', 'f.cases', 'p.mean', 'org_units.name AS facility', 'diseases.name AS disease'])
            ->map(fn ($r) => $this->row('implausible_jump', $r->org_unit_id, 'warning', $week->toDateString(),
                $r->facility.' reported '.$r->cases.' cases of '.$r->disease.' against an average of '.round($r->mean, 1).'. Check the entry.', (string) $r->disease_id))->all();
    }

    /** The same person with the same disease notified twice within 14 days. */
    private function duplicateCases(): array
    {
        return DB::table('cases AS a')->join('cases AS b', fn ($j) => $j->on('a.person_id', '=', 'b.person_id')->on('a.disease_id', '=', 'b.disease_id')->whereColumn('a.id', '<', 'b.id'))
            ->whereRaw('ABS(DATEDIFF(a.notification_date, b.notification_date)) <= 14')->where('a.classification', '!=', 'discarded')->where('b.classification', '!=', 'discarded')
            ->join('diseases', 'diseases.id', '=', 'a.disease_id')
            ->get(['b.reporting_org_unit_id AS unit', 'a.id AS first', 'b.id AS second', 'b.epi_week', 'diseases.name AS disease'])
            ->map(fn ($r) => $this->row('duplicate_case', $r->unit, 'warning', $r->epi_week, 'Cases '.$r->first.' and '.$r->second.' ('.$r->disease.') may be the same illness notified twice.', $r->first.'-'.$r->second))->all();
    }
}
