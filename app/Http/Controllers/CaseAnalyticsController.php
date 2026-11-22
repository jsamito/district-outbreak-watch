<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\Disease;
use App\Services\AggregateQuery;
use App\Services\CaseAnalytics;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Aggregate views built from case records. No case identifiers appear on this screen. */
class CaseAnalyticsController extends Controller
{
    use ResolvesFilters;

    public function index(Request $request, CaseAnalytics $analytics, AggregateQuery $agg): View
    {
        $area = $this->area($request);
        $disease = $request->filled('disease') ? Disease::where('code', $request->input('disease'))->where('case_based', true)->first() : null;
        [$from, $to] = $this->range($request, 26);
        if (! $request->filled('to')) {
            $to = $agg->currentWeek();
        }

        $q = $analytics->query($request->user(), $area, $disease, $from, $to);
        $totals = $analytics->totals($q);
        $delay = $analytics->notificationDelay($q);
        $lab = $analytics->laboratory($q);

        Audit::log(Audit::DATA_ACCESS, 'case_analytics_viewed', null, ['area' => $area->id, 'disease' => $disease?->code, 'from' => $from->toDateString(), 'to' => $to->toDateString()]);

        return view('cases.analytics', $this->filterData($request, $area, $disease, $from, $to) + [
            'diseases' => Disease::where('case_based', true)->orderBy('priority')->orderBy('name')->get(), 'allDiseases' => true,
            'title' => 'Case analytics', 'subtitle' => ($disease?->name ?? 'All case-based diseases').' · '.$area->name,
            'totals' => $totals, 'curve' => $analytics->epiCurve($q), 'pyramid' => $analytics->ageSex($q), 'fatality' => $analytics->fatality($q),
            'delay' => $delay, 'lab' => $lab, 'floor' => config('surveillance.small_number_floor'),
        ]);
    }
}
