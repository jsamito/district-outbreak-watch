<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Disease;
use App\Models\OrgUnit;
use App\Models\User;
use App\Services\AggregateQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait ResolvesFilters
{
    /** Area chosen by the user (or their own root), always authorised. */
    protected function area(Request $request): OrgUnit
    {
        /** @var User $user */
        $user = $request->user();
        $unit = null;
        if ($request->filled('facility')) {
            $unit = OrgUnit::find($request->integer('facility'));
        } elseif ($request->filled('area')) {
            $unit = OrgUnit::find($request->integer('area'));
        }
        if (! $unit) {
            $unit = $user->orgUnits()->orderBy('level')->orderBy('name')->first();
        }
        abort_unless($unit, 403, 'No area of responsibility is assigned to this account.');
        $this->authorize('view', $unit);

        return $unit;
    }

    /** District-or-above choices inside the user's scope, for the area selector. */
    protected function areaOptions(User $user): Collection
    {
        return $user->scopedOrgUnits()->whereIn('type', [OrgUnit::COUNTRY, OrgUnit::REGION, OrgUnit::DISTRICT])
            ->orderBy('level')->orderBy('name')->get(['id', 'name', 'type', 'path']);
    }

    /** Sentinel facilities under the chosen district (or the district of the chosen facility). */
    protected function facilityOptions(User $user, OrgUnit $area): Collection
    {
        $district = $area->type === OrgUnit::FACILITY ? $area->parent : ($area->type === OrgUnit::DISTRICT ? $area : null);
        if (! $district) {
            return collect();
        }

        return $user->scopedOrgUnits()->where('type', OrgUnit::FACILITY)->where('parent_id', $district->id)
            ->whereIn('id', fn ($q) => $q->select('org_unit_id')->from('facility_profiles')->where('sentinel', true))
            ->orderBy('name')->get(['id', 'name']);
    }

    protected function disease(Request $request, string $default = 'malaria'): Disease
    {
        $d = $request->filled('disease') ? Disease::where('code', $request->input('disease'))->first() : null;

        return $d ?? Disease::where('code', $default)->first() ?? Disease::orderBy('priority')->firstOrFail();
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} week-aligned from/to */
    protected function range(Request $request, int $defaultWeeks = 52): array
    {
        $latest = app(AggregateQuery::class)->latestWeek() ?? CarbonImmutable::today()->startOfWeek();
        $to = $request->filled('to') ? $this->parseWeek($request->input('to'), $latest) : $latest;
        $current = app(AggregateQuery::class)->currentWeek();
        $to = $to->greaterThan($current) ? $current : $to;
        $from = $request->filled('from') ? $this->parseWeek($request->input('from'), $to->subWeeks($defaultWeeks - 1)) : $to->subWeeks($defaultWeeks - 1);
        if ($from->greaterThan($to)) {
            $from = $to->subWeeks($defaultWeeks - 1);
        }

        return [$from, $to];
    }

    private function parseWeek(string $value, CarbonImmutable $fallback): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value)->startOfWeek();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    protected function filterData(Request $request, OrgUnit $area, ?Disease $disease, CarbonImmutable $from, CarbonImmutable $to, bool $showDiseases = true): array
    {
        $user = $request->user();

        return [
            'filters' => ['area' => $area->type === OrgUnit::FACILITY ? $area->parent_id : $area->id, 'facility' => $area->type === OrgUnit::FACILITY ? $area->id : null,
                'disease' => $disease?->code, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'areaOptions' => $this->areaOptions($user),
            'facilityOptions' => $this->facilityOptions($user, $area),
            'diseases' => $showDiseases ? Disease::orderBy('priority')->orderBy('name')->get() : collect(),
            'area' => $area, 'disease' => $disease, 'from' => $from, 'to' => $to,
        ];
    }
}
