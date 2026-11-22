<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\Disease;
use App\Models\OrgUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Case-level analysis shown as aggregates only: no row ever leaves this class with a person or a case identifier.
 * Every query is limited to the facilities the user may see and to the chosen area.
 */
class CaseAnalytics
{
    public const AGE_BANDS = ['0-4', '5-14', '15-24', '25-44', '45-64', '65+'];
    public const DELAY_BANDS = ['Same day', '1 day', '2 days', '3 days', '4-6 days', '7-13 days', '14+ days'];

    public function query(User $user, OrgUnit $area, ?Disease $disease, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return CaseRecord::visibleTo($user)
            ->where('cases.classification', '!=', 'discarded')
            ->whereIn('cases.reporting_org_unit_id', OrgUnit::within($area)->select('id'))
            ->whereBetween('cases.notification_date', [$from->toDateString(), $to->endOfWeek()->toDateString()])
            ->when($disease, fn ($q) => $q->where('cases.disease_id', $disease->id));
    }

    /**
     * Epidemic curve by date of symptom onset, stacked by classification. Daily for a short period, weekly for a long one.
     *
     * @return array{labels:string[],series:array<int,array{label:string,data:int[]}>,unit:string,total:int}
     */
    public function epiCurve(Builder $q): array
    {
        $bounds = (clone $q)->selectRaw('MIN(onset_date) lo, MAX(onset_date) hi')->first();
        if (! $bounds || ! $bounds->lo) {
            return ['labels' => [], 'series' => [], 'unit' => 'day', 'total' => 0];
        }
        $lo = CarbonImmutable::parse($bounds->lo);
        $hi = CarbonImmutable::parse($bounds->hi);
        $daily = $lo->diffInDays($hi) <= 100;
        $bucket = $daily ? 'cases.onset_date' : 'DATE_SUB(cases.onset_date, INTERVAL WEEKDAY(cases.onset_date) DAY)';
        $rows = (clone $q)->selectRaw("$bucket AS b, cases.classification AS c, COUNT(*) AS n")->groupBy('b', 'c')->get();

        $labels = [];
        for ($d = $daily ? $lo : $lo->startOfWeek(); $d->lte($hi); $d = $daily ? $d->addDay() : $d->addWeek()) {
            $labels[] = $d->toDateString();
        }
        $grid = array_fill_keys(['confirmed', 'probable', 'suspected'], array_fill_keys($labels, 0));
        foreach ($rows as $r) {
            $key = CarbonImmutable::parse($r->b)->toDateString();
            if (isset($grid[$r->c][$key])) {
                $grid[$r->c][$key] = (int) $r->n;
            }
        }
        $series = [];
        foreach ($grid as $class => $values) {
            $series[] = ['label' => ucfirst($class), 'data' => array_values($values)];
        }

        return ['labels' => $labels, 'series' => $series, 'unit' => $daily ? 'day' : 'week', 'total' => (int) $rows->sum('n')];
    }

    /**
     * Cases by age band and sex, for a population pyramid. Cases with unknown sex are counted apart.
     *
     * @return array{bands:string[],male:int[],female:int[],unknown_sex:int,total:int}
     */
    public function ageSex(Builder $q): array
    {
        $age = '(YEAR(cases.notification_date) - persons.birth_year)';
        $band = "CASE WHEN $age < 5 THEN '0-4' WHEN $age < 15 THEN '5-14' WHEN $age < 25 THEN '15-24' WHEN $age < 45 THEN '25-44' WHEN $age < 65 THEN '45-64' ELSE '65+' END";
        $rows = (clone $q)->join('persons', 'persons.id', '=', 'cases.person_id')->selectRaw("$band AS band, persons.sex AS sex, COUNT(*) AS n")->groupBy('band', 'sex')->get();
        $m = array_fill_keys(self::AGE_BANDS, 0);
        $f = $m;
        $unknown = 0;
        foreach ($rows as $r) {
            if ($r->sex === 'M') {
                $m[$r->band] = (int) $r->n;
            } elseif ($r->sex === 'F') {
                $f[$r->band] = (int) $r->n;
            } else {
                $unknown += (int) $r->n;
            }
        }

        return ['bands' => self::AGE_BANDS, 'male' => array_values($m), 'female' => array_values($f), 'unknown_sex' => $unknown, 'total' => array_sum($m) + array_sum($f) + $unknown];
    }

    /**
     * Case fatality by disease: deaths divided by all non-discarded cases, with a 95% Wilson interval so that small
     * numbers look as uncertain as they are. Cases whose outcome is still unknown stay in the denominator and are shown.
     *
     * @return array<int,array{disease:string,cases:int,deaths:int,unknown:int,cfr:?float,low:?float,high:?float}>
     */
    public function fatality(Builder $q): array
    {
        $rows = (clone $q)->join('diseases', 'diseases.id', '=', 'cases.disease_id')
            ->selectRaw("diseases.name AS disease, COUNT(*) AS cases, SUM(cases.outcome = 'died') AS deaths, SUM(cases.outcome = 'unknown') AS unknown")
            ->groupBy('diseases.id', 'diseases.name')->orderByDesc('cases')->get();

        return $rows->map(function ($r) {
            $n = (int) $r->cases;
            $d = (int) $r->deaths;
            [$low, $high] = self::wilson($d, $n);

            return ['disease' => $r->disease, 'cases' => $n, 'deaths' => $d, 'unknown' => (int) $r->unknown, 'cfr' => $n ? round($d / $n * 100, 1) : null, 'low' => $low, 'high' => $high];
        })->all();
    }

    /** @return array{0:?float,1:?float} percentage bounds */
    public static function wilson(int $x, int $n, float $z = 1.96): array
    {
        if ($n === 0) {
            return [null, null];
        }
        $p = $x / $n;
        $den = 1 + $z * $z / $n;
        $centre = ($p + $z * $z / (2 * $n)) / $den;
        $half = $z * sqrt($p * (1 - $p) / $n + $z * $z / (4 * $n * $n)) / $den;

        return [round(max(0, $centre - $half) * 100, 1), round(min(1, $centre + $half) * 100, 1)];
    }

    /**
     * Days from symptom onset to notification.
     *
     * @return array{bands:string[],counts:int[],median:?float,total:int,within_3_days:?float}
     */
    public function notificationDelay(Builder $q): array
    {
        $rows = (clone $q)->selectRaw('GREATEST(DATEDIFF(cases.notification_date, cases.onset_date), 0) AS d, COUNT(*) AS n')->groupBy('d')->orderBy('d')->get();
        $counts = array_fill(0, count(self::DELAY_BANDS), 0);
        $total = 0;
        foreach ($rows as $r) {
            $d = (int) $r->d;
            $i = $d <= 3 ? $d : ($d <= 6 ? 4 : ($d <= 13 ? 5 : 6));
            $counts[$i] += (int) $r->n;
            $total += (int) $r->n;
        }
        $median = null;
        if ($total) {
            $seen = 0;
            $half = $total / 2;
            foreach ($rows as $r) {
                $seen += (int) $r->n;
                if ($seen >= $half) {
                    $median = (float) $r->d;
                    break;
                }
            }
        }

        return ['bands' => self::DELAY_BANDS, 'counts' => $counts, 'median' => $median, 'total' => $total,
            'within_3_days' => $total ? round(array_sum(array_slice($counts, 0, 4)) / $total * 100, 1) : null];
    }

    /**
     * Laboratory follow-up by disease: how many cases have a finished test, how many of those are positive.
     *
     * @return array{rows:array<int,array{disease:string,cases:int,tested:int,positive:int,pending:int,tested_pct:?float,positive_pct:?float}>,median_turnaround:?float}
     */
    public function laboratory(Builder $q): array
    {
        $has = fn (string $cond) => "EXISTS (SELECT 1 FROM lab_results l WHERE l.case_id = cases.id AND $cond)";
        $rows = (clone $q)->join('diseases', 'diseases.id', '=', 'cases.disease_id')
            ->selectRaw('diseases.name AS disease, COUNT(*) AS cases, SUM('.$has("l.result <> 'pending'").') AS tested, SUM('.$has("l.result = 'positive'").') AS positive, SUM('.$has("l.result = 'pending'").') AS pending')
            ->groupBy('diseases.id', 'diseases.name')->orderByDesc('cases')->get()
            ->map(fn ($r) => ['disease' => $r->disease, 'cases' => (int) $r->cases, 'tested' => (int) $r->tested, 'positive' => (int) $r->positive, 'pending' => (int) $r->pending,
                'tested_pct' => $r->cases ? round($r->tested / $r->cases * 100, 1) : null, 'positive_pct' => $r->tested ? round($r->positive / $r->tested * 100, 1) : null])->all();

        $turn = DB::table('lab_results')->whereIn('case_id', (clone $q)->select('cases.id'))->whereNotNull('resulted_at')
            ->selectRaw('DATEDIFF(resulted_at, collected_at) AS d')->orderBy('d')->pluck('d')->all();
        $median = $turn ? (float) $turn[intdiv(count($turn) - 1, 2)] : null;

        return ['rows' => $rows, 'median_turnaround' => $median];
    }

    /** @return array{total:int,deaths:int,hospitalised:int,confirmed:int} */
    public function totals(Builder $q): array
    {
        $r = (clone $q)->selectRaw("COUNT(*) AS total, SUM(cases.outcome = 'died') AS deaths, SUM(cases.hospitalised) AS hosp, SUM(cases.classification = 'confirmed') AS conf")->first();

        return ['total' => (int) $r->total, 'deaths' => (int) $r->deaths, 'hospitalised' => (int) $r->hosp, 'confirmed' => (int) $r->conf];
    }
}
