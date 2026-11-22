<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\Indicator;
use App\Models\OrgUnit;
use App\Services\AggregateQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportingController extends Controller
{
    use ResolvesFilters;

    public function __construct(private AggregateQuery $q) {}

    public function index(Request $request): View
    {
        $area = $this->area($request);
        $latest = $this->q->latestWeek();
        $weeksN = 12;
        $from = $latest->subWeeks($weeksN - 1);
        $weeks = [];
        for ($i = 0; $i < $weeksN; $i++) {
            $weeks[] = $from->addWeeks($i)->toDateString();
        }

        $children = $area->type === OrgUnit::FACILITY ? collect() : $area->children()->where('active', true)->orderBy('name')->get(['id', 'name', 'type']);
        if ($area->type === OrgUnit::DISTRICT) {
            $sentinel = DB::table('facility_profiles')->where('sentinel', true)->pluck('org_unit_id')->all();
            $children = $children->whereIn('id', $sentinel)->values();
        }
        $matrix = [];
        $rows = DB::table('indicator_values')
            ->where(['indicator_id' => Indicator::idFor(Indicator::REPORTING), 'period_type' => 'week'])
            ->whereIn('org_unit_id', $children->pluck('id'))->whereBetween('period_start', [$from->toDateString(), $latest->toDateString()])
            ->get(['org_unit_id', 'period_start', 'value']);
        foreach ($rows as $r) {
            $matrix[$r->org_unit_id][$r->period_start] = (float) $r->value;
        }
        // Facility rows also show timeliness: received on time, received late, or missing.
        $status = [];
        if ($children->isNotEmpty() && $children->first()->type === OrgUnit::FACILITY) {
            foreach (DB::table('reporting_statuses')->whereIn('org_unit_id', $children->pluck('id'))->whereBetween('week_start', [$from->toDateString(), $latest->toDateString()])->get(['org_unit_id', 'week_start', 'received', 'on_time']) as $r) {
                $status[$r->org_unit_id][$r->week_start] = ! $r->received ? 'missing' : ($r->on_time ? 'on_time' : 'late');
            }
        }
        $avg = fn ($id) => isset($matrix[$id]) ? array_sum($matrix[$id]) / count($matrix[$id]) : null;
        $children = $children->map(fn ($c) => (object) ['id' => $c->id, 'name' => $c->name, 'type' => $c->type, 'avg' => $avg($c->id)])->sortBy(fn ($c) => $c->avg ?? 999)->values();

        $trend = $this->q->reportingSeries($area, $latest->subWeeks(25), $latest);
        $timely = DB::table('reporting_statuses as rs')->join('org_units as o', 'o.id', '=', 'rs.org_unit_id')
            ->where(fn ($w) => $w->where('o.path', $area->path)->orWhere('o.path', 'like', OrgUnit::escapeLike($area->path).'/%'))
            ->where('rs.week_start', $latest->toDateString())
            ->selectRaw('SUM(rs.received) AS received, SUM(rs.on_time) AS on_time, COUNT(*) AS expected')->first();

        return view('reporting', [
            'title' => 'Reporting completeness', 'subtitle' => $area->name, 'area' => $area, 'weeks' => $weeks, 'matrix' => $matrix, 'status' => $status,
            'children' => $children, 'trend' => $trend, 'timely' => $timely, 'latest' => $latest, 'areaChoices' => $this->areaOptions($request->user()),
        ]);
    }
}
