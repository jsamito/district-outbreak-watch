<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\ExternalMetric;
use App\Services\AggregateQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttentionController extends Controller
{
    use ResolvesFilters;

    public function __construct(private AggregateQuery $q) {}

    public function index(Request $request): View
    {
        $area = $this->area($request);
        $disease = $this->disease($request, 'measles');
        [$from, $to] = $this->range($request, 52);

        $views = ExternalMetric::where(['disease_id' => $disease->id, 'source' => 'wikipedia', 'metric' => 'pageviews'])
            ->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])->orderBy('period_start')->pluck('value', 'period_start')->all();
        $cases = $this->q->series($area, $disease->id, $from, $to);
        $weeks = array_values(array_unique(array_merge(array_keys($views), array_keys($cases))));
        sort($weeks);
        $base = array_slice($views, 0, 8);
        $baseMean = $base ? array_sum($base) / count($base) : null;

        return view('attention', $this->filterData($request, $area, $disease, $from, $to) + [
            'title' => 'Public attention', 'subtitle' => $disease->name,
            'labels' => $weeks, 'views' => array_map(fn ($w) => $views[$w] ?? null, $weeks), 'cases' => array_map(fn ($w) => $cases[$w] ?? null, $weeks),
            'index' => array_map(fn ($w) => isset($views[$w], $baseMean) && $baseMean > 0 ? round($views[$w] / $baseMean, 2) : null, $weeks),
        ]);
    }
}
