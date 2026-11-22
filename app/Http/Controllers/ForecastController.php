<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\ForecastRun;
use App\Services\AggregateQuery;
use App\Services\Forecaster;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForecastController extends Controller
{
    use ResolvesFilters;

    public function __construct(private AggregateQuery $q, private Forecaster $forecaster) {}

    public function index(Request $request): View
    {
        $area = $this->area($request);
        $disease = $this->disease($request, 'measles');
        $latest = $this->q->latestWeek();
        $history = $this->q->series($area, $disease->id, $latest->subWeeks(103), $latest);
        ksort($history);

        $run = ForecastRun::where(['org_unit_id' => $area->id, 'disease_id' => $disease->id])->whereDate('trained_through', $latest->toDateString())->with('points')->first();
        if ($run) {
            $result = ['label' => Forecaster::METHODS[$run->method], 'points' => $run->points->map(fn ($p) => $p->only('point', 'lower80', 'upper80', 'lower95', 'upper95'))->all(),
                'backtest' => $run->backtest, 'useful' => (bool) ($run->backtest['useful'] ?? false), 'params' => $run->params];
        } else {
            $res = $this->forecaster->forecast(array_values($history));
            $result = $res ? ['label' => $res['label'], 'points' => $res['points'], 'backtest' => $res['backtest'], 'useful' => $res['useful'], 'params' => $res['params']] : null;
        }

        $shown = array_slice($history, -26, null, true);
        $futureWeeks = [];
        foreach (($result['points'] ?? []) as $i => $p) {
            $futureWeeks[] = $latest->addWeeks($i + 1)->toDateString();
        }

        return view('forecasts', $this->filterData($request, $area, $disease, $latest->subWeeks(25), $latest) + [
            'title' => 'Forecasts', 'subtitle' => $disease->name.' · '.$area->name, 'result' => $result, 'historyLabels' => array_keys($shown), 'history' => array_values($shown), 'futureWeeks' => $futureWeeks,
        ]);
    }
}
