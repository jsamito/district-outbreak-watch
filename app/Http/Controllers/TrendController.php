<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\Annotation;
use App\Services\AggregateQuery;
use App\Services\OutbreakDetector;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrendController extends Controller
{
    use ResolvesFilters;

    public function __construct(private AggregateQuery $q, private OutbreakDetector $detector) {}

    public function index(Request $request): View
    {
        $area = $this->area($request);
        $disease = $this->disease($request, 'measles');
        [$from, $to] = $this->range($request, 52);

        $series = $this->q->series($area, $disease->id, $from->subWeeks(10), $to);
        $eval = $this->detector->evaluate($series);
        $weeks = array_values(array_filter(array_keys($series), fn ($w) => $w >= $from->toDateString()));
        $lastYear = $this->q->series($area, $disease->id, $from->subWeeks(52), $to->subWeeks(52));

        $rows = [];
        $ma = [];
        foreach ($weeks as $i => $w) {
            $window = array_slice(array_map(fn ($x) => $series[$x], $weeks), max(0, $i - 3), min(4, $i + 1));
            $ma[] = round(array_sum($window) / max(count($window), 1), 1);
            $pop = $this->q->population($area, (int) substr($w, 0, 4));
            $rows[] = (object) [
                'week' => $w, 'cases' => $series[$w], 'incidence' => $pop ? $series[$w] / $pop * 100000 : null, 'population' => $pop,
                'expected' => $eval[$w]['expected'] ?? null, 'upper' => $eval[$w]['upper'] ?? null, 'signal' => (bool) ($eval[$w]['signal'] ?? false),
            ];
        }
        $prevYear = array_map(fn ($w) => $lastYear[CarbonImmutable::parse($w)->subWeeks(52)->toDateString()] ?? null, $weeks);

        return view('trends', $this->filterData($request, $area, $disease, $from, $to) + [
            'title' => 'Trends', 'subtitle' => $disease->name.' · '.$area->name,
            'labels' => $weeks, 'cases' => array_map(fn ($w) => $series[$w], $weeks), 'ma' => $ma, 'prevYear' => $prevYear,
            'signals' => array_map(fn ($w) => (bool) ($eval[$w]['signal'] ?? false), $weeks), 'rows' => $rows,
            'annotations' => Annotation::for($area, $disease)->with('user:id,name')->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])->orderBy('period_start')->get(),
            'total' => array_sum(array_map(fn ($w) => $series[$w], $weeks)),
        ]);
    }
}
