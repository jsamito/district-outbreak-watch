<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\OrgUnit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Read access to stored aggregates. Callers must already have authorised the org units they pass in. */
class AggregateQuery
{
    /** Start of the week in progress; live notifications land here. */
    public function currentWeek(): CarbonImmutable
    {
        return CarbonImmutable::today()->startOfWeek();
    }

    /** Latest complete week with data: the week dashboards, signals and alerts are based on. */
    public function latestWeek(): ?CarbonImmutable
    {
        $d = DB::table('indicator_values')->where('indicator_id', Indicator::idFor(Indicator::CASES))->where('period_type', 'week')
            ->where('period_start', '<', $this->currentWeek()->toDateString())->max('period_start');

        return $d ? CarbonImmutable::parse($d) : null;
    }

    /** @return array<string,float> week_start => cases (weeks with no report are absent) */
    public function series(OrgUnit $unit, int $diseaseId, CarbonInterface $from, CarbonInterface $to): array
    {
        return DB::table('indicator_values')
            ->where(['org_unit_id' => $unit->id, 'indicator_id' => Indicator::idFor(Indicator::CASES), 'dimension_id' => $diseaseId, 'period_type' => 'week'])
            ->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])
            ->orderBy('period_start')->pluck('value', 'period_start')->map(fn ($v) => (float) $v)->all();
    }

    /** @return array<int,array<string,float>> unitId => [week => cases] */
    public function seriesForUnits(array $unitIds, int $diseaseId, CarbonInterface $from, CarbonInterface $to): array
    {
        $out = [];
        $rows = DB::table('indicator_values')
            ->where(['indicator_id' => Indicator::idFor(Indicator::CASES), 'dimension_id' => $diseaseId, 'period_type' => 'week'])
            ->whereIn('org_unit_id', $unitIds)
            ->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])
            ->get(['org_unit_id', 'period_start', 'value']);
        foreach ($rows as $r) {
            $out[$r->org_unit_id][$r->period_start] = (float) $r->value;
        }

        return $out;
    }

    /** Population of an area (sum of its districts); null for a single facility. */
    public function population(OrgUnit $unit, int $year): ?int
    {
        if ($unit->type === OrgUnit::FACILITY) {
            return null;
        }
        $q = DB::table('populations as p')->join('org_units as o', 'o.id', '=', 'p.org_unit_id')
            ->where('p.year', $year)->where('o.type', OrgUnit::DISTRICT)
            ->where(fn ($w) => $w->where('o.path', $unit->path)->orWhere('o.path', 'like', OrgUnit::escapeLike($unit->path).'/%'));

        return (int) $q->sum('p.total') ?: null;
    }

    /** @return array<int,int> districtId => population for the year */
    public function districtPopulations(int $year, ?OrgUnit $within = null): array
    {
        $q = DB::table('populations as p')->join('org_units as o', 'o.id', '=', 'p.org_unit_id')->where('p.year', $year)->where('o.type', OrgUnit::DISTRICT);
        if ($within) {
            $q->where(fn ($w) => $w->where('o.path', $within->path)->orWhere('o.path', 'like', OrgUnit::escapeLike($within->path).'/%'));
        }

        return $q->pluck('p.total', 'p.org_unit_id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Totals for each child of $unit over the period.
     *
     * @return Collection<int,object{id:int,name:string,type:string,code:string,cases:float,population:?int,incidence:?float,reports:?float}>
     */
    public function childTotals(OrgUnit $unit, int $diseaseId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $children = $unit->children()->where('active', true)->get(['id', 'name', 'type', 'code', 'path', 'lat', 'lng']);
        if ($unit->type === OrgUnit::DISTRICT) {
            $sentinel = DB::table('facility_profiles')->where('sentinel', true)->pluck('org_unit_id')->all();
            $children = $children->whereIn('id', $sentinel)->values();
        }
        $ids = $children->pluck('id')->all();
        $cases = DB::table('indicator_values')
            ->where(['indicator_id' => Indicator::idFor(Indicator::CASES), 'dimension_id' => $diseaseId, 'period_type' => 'week'])
            ->whereIn('org_unit_id', $ids)->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])
            ->groupBy('org_unit_id')->selectRaw('org_unit_id, SUM(value) AS cases')->pluck('cases', 'org_unit_id');
        $pops = [];
        if ($unit->type !== OrgUnit::DISTRICT) {
            $year = (int) $to->year;
            foreach ($children as $c) {
                $pops[$c->id] = $this->population($c, $year);
            }
        }
        $reporting = DB::table('indicator_values')
            ->where(['indicator_id' => Indicator::idFor(Indicator::REPORTING), 'period_type' => 'week'])
            ->whereIn('org_unit_id', $ids)->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])
            ->groupBy('org_unit_id')->selectRaw('org_unit_id, 100 * SUM(numerator) / NULLIF(SUM(denominator), 0) AS rate')->pluck('rate', 'org_unit_id');

        return $children->map(function ($c) use ($cases, $pops, $reporting) {
            $n = (float) ($cases[$c->id] ?? 0);
            $pop = $pops[$c->id] ?? null;

            return (object) [
                'id' => $c->id, 'name' => $c->name, 'type' => $c->type, 'code' => $c->code, 'cases' => $n,
                'population' => $pop, 'incidence' => $pop ? $n / $pop * 100000 : null,
                'reports' => isset($reporting[$c->id]) ? (float) $reporting[$c->id] : null,
            ];
        })->values();
    }

    /** Sum of cases in one area for one disease over a period. */
    public function total(OrgUnit $unit, int $diseaseId, CarbonInterface $from, CarbonInterface $to): float
    {
        return array_sum($this->series($unit, $diseaseId, $from, $to));
    }

    /** @return array<string,float> week => completeness % */
    public function reportingSeries(OrgUnit $unit, CarbonInterface $from, CarbonInterface $to): array
    {
        return DB::table('indicator_values')
            ->where(['org_unit_id' => $unit->id, 'indicator_id' => Indicator::idFor(Indicator::REPORTING), 'period_type' => 'week'])
            ->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])
            ->orderBy('period_start')->pluck('value', 'period_start')->map(fn ($v) => (float) $v)->all();
    }

    /** @return array<string,array{doses:float,target:float,value:?float}> month => coverage for one antigen */
    public function vaccinationSeries(OrgUnit $unit, int $antigenId, CarbonInterface $from, CarbonInterface $to): array
    {
        $out = [];
        $rows = DB::table('indicator_values')
            ->where(['org_unit_id' => $unit->id, 'indicator_id' => Indicator::idFor(Indicator::VACCINATION), 'dimension_id' => $antigenId, 'period_type' => 'month'])
            ->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])->orderBy('period_start')->get(['period_start', 'numerator', 'denominator', 'value']);
        foreach ($rows as $r) {
            $out[$r->period_start] = ['doses' => (float) $r->numerator, 'target' => (float) $r->denominator, 'value' => $r->value === null ? null : (float) $r->value];
        }

        return $out;
    }

    /** Coverage over a period (sum of doses / sum of targets) per antigen for one area. @return array<int,array{doses:float,target:float,coverage:?float}> */
    public function vaccinationByAntigen(OrgUnit $unit, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = DB::table('indicator_values')
            ->where(['org_unit_id' => $unit->id, 'indicator_id' => Indicator::idFor(Indicator::VACCINATION), 'period_type' => 'month'])
            ->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])
            ->groupBy('dimension_id')->selectRaw('dimension_id, SUM(numerator) AS doses, SUM(denominator) AS target')->get();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->dimension_id] = ['doses' => (float) $r->doses, 'target' => (float) $r->target, 'coverage' => $r->target > 0 ? 100 * $r->doses / $r->target : null];
        }

        return $out;
    }

    /** Coverage for one antigen for each child area over a period. @return Collection<int,object> */
    public function vaccinationByChild(OrgUnit $unit, int $antigenId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $children = $unit->children()->where('type', '!=', OrgUnit::FACILITY)->orderBy('name')->get(['id', 'name', 'type']);
        $rows = DB::table('indicator_values')
            ->where(['indicator_id' => Indicator::idFor(Indicator::VACCINATION), 'dimension_id' => $antigenId, 'period_type' => 'month'])
            ->whereIn('org_unit_id', $children->pluck('id'))->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])
            ->groupBy('org_unit_id')->selectRaw('org_unit_id, SUM(numerator) AS doses, SUM(denominator) AS target')->get()->keyBy('org_unit_id');

        return $children->map(function ($c) use ($rows) {
            $r = $rows[$c->id] ?? null;

            return (object) ['id' => $c->id, 'name' => $c->name, 'type' => $c->type, 'doses' => $r ? (float) $r->doses : 0.0, 'target' => $r ? (float) $r->target : 0.0,
                'coverage' => $r && $r->target > 0 ? 100 * $r->doses / $r->target : null];
        });
    }
}
