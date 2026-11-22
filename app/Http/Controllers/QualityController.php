<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Services\AggregateQuery;
use App\Services\ControlChartService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QualityController extends Controller
{
    use ResolvesFilters;

    public function __construct(private AggregateQuery $q, private ControlChartService $chart) {}

    public function index(Request $request): View
    {
        $area = $this->area($request);
        $disease = $this->disease($request, 'measles');
        [$from, $to] = $this->range($request, 64);

        $counts = $this->q->series($area, $disease->id, $from, $to);
        $popByYear = [];
        $population = [];
        foreach (array_keys($counts) as $w) {
            $y = (int) substr($w, 0, 4);
            $popByYear[$y] ??= $this->q->population($area, $y);
            $population[$w] = $popByYear[$y] ?? 0;
        }
        $result = $this->chart->build($counts, $population);
        $points = collect($result['points']);

        return view('quality', $this->filterData($request, $area, $disease, $from, $to) + [
            'title' => 'Quality control chart', 'subtitle' => $disease->name.' · '.$area->name,
            'points' => $points, 'centre' => $result['centre'], 'rule' => $result['rule'], 'baselineWeeks' => $result['baseline_weeks'],
            'flagged' => $points->filter(fn ($p) => $p['above_ucl'] || $p['shift'])->values(), 'noPopulation' => ! array_filter($population),
        ]);
    }
}
