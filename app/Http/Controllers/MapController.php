<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\OrgUnit;
use App\Services\AggregateQuery;
use App\Services\SignalService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MapController extends Controller
{
    use ResolvesFilters;

    public function __construct(private AggregateQuery $q, private SignalService $signals) {}

    public function index(Request $request): View
    {
        $area = $this->area($request);
        $disease = $this->disease($request, 'measles');
        $latest = $this->q->latestWeek();
        [$from, $to] = $this->range($request, 4);
        $metric = $request->input('metric') === 'cases' ? 'cases' : 'incidence';

        // Map of districts inside the area; for a district or facility the map shows its own district.
        $mapRoot = $area->type === OrgUnit::FACILITY ? $area->parent : $area;
        $districts = $mapRoot->type === OrgUnit::DISTRICT
            ? collect([$mapRoot])
            : OrgUnit::within($mapRoot)->where('type', OrgUnit::DISTRICT)->orderBy('name')->get();
        $districts = $districts->filter(fn ($d) => $request->user()->canAccessOrgUnit($d))->values();

        $pops = $this->q->districtPopulations((int) $to->year);
        $series = $this->q->seriesForUnits($districts->pluck('id')->all(), $disease->id, $from, $to);
        $reporting = [];
        $rows = $districts->map(function ($d) use ($series, $pops, &$reporting, $from, $to) {
            $cases = array_sum($series[$d->id] ?? []);
            $pop = $pops[$d->id] ?? null;

            return (object) ['id' => $d->id, 'name' => $d->name, 'cases' => $cases, 'population' => $pop, 'incidence' => $pop ? $cases / $pop * 100000 : null];
        });
        $flags = $this->signals->forUnits($districts->map(fn ($d) => (object) ['id' => $d->id, 'name' => $d->name, 'type' => $d->type]), $latest)
            ->where('disease_id', $disease->id)->keyBy('unit_id');

        $values = [];
        foreach ($rows as $r) {
            $v = $metric === 'cases' ? $r->cases : $r->incidence;
            $values[$r->id] = ['v' => $v === null ? null : round($v, 2),
                'label' => number_format($r->cases).' cases'.($r->incidence !== null ? ' · '.number_format($r->incidence, 1).' per 100,000' : '')];
        }
        $sorted = $rows->sortByDesc($metric)->values();

        $facilities = $area->type === OrgUnit::DISTRICT || $area->type === OrgUnit::FACILITY
            ? $this->q->childTotals($mapRoot, $disease->id, $from, $to)->sortByDesc('cases')->values() : collect();

        return view('map', $this->filterData($request, $area, $disease, $from, $to) + [
            'title' => 'Map and comparison', 'subtitle' => $disease->name.' · '.$area->name,
            'metric' => $metric, 'rows' => $rows, 'sorted' => $sorted, 'values' => $values, 'flags' => $flags->keys()->mapWithKeys(fn ($id) => [$id => true]),
            'flagRows' => $flags, 'facilities' => $facilities, 'mapRoot' => $mapRoot,
        ]);
    }
}
