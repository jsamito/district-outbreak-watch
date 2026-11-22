<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\Antigen;
use App\Models\OrgUnit;
use App\Models\ReferenceEstimate;
use App\Services\AggregateQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VaccinationController extends Controller
{
    use ResolvesFilters;

    public function __construct(private AggregateQuery $q) {}

    public function index(Request $request): View
    {
        $area = $this->area($request);
        $antigens = Antigen::orderBy('id')->get();
        $antigen = $antigens->firstWhere('code', $request->input('antigen')) ?? $antigens->firstWhere('code', 'MCV1') ?? $antigens->first();
        $latest = $this->q->latestWeek() ?? CarbonImmutable::today();
        $to = $latest->startOfMonth();
        $months = max(3, min(36, $request->integer('months', 12)));
        $from = $to->subMonths($months - 1);

        $byChild = $area->type === OrgUnit::FACILITY ? collect() : $this->q->vaccinationByChild($area, $antigen->id, $from, $to);
        $trend = $this->q->vaccinationSeries($area, $antigen->id, $to->subMonths(23), $to);
        $overall = $this->q->vaccinationByAntigen($area, $from, $to);
        $who = ReferenceEstimate::where('antigen_id', $antigen->id)->orderByDesc('year')->first();

        return view('vaccination', $this->filterData($request, $area, null, $from, $to, false) + [
            'title' => 'Vaccination coverage', 'subtitle' => $antigen->name.' · '.$area->name,
            'antigens' => $antigens, 'antigen' => $antigen, 'months' => $months, 'byChild' => $byChild->sortByDesc('coverage')->values(),
            'trend' => $trend, 'overall' => $overall, 'who' => $who, 'latestMonth' => $to,
        ]);
    }
}
