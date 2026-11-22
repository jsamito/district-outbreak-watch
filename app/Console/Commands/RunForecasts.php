<?php

namespace App\Console\Commands;

use App\Models\Disease;
use App\Models\ForecastPoint;
use App\Models\ForecastRun;
use App\Models\OrgUnit;
use App\Services\AggregateQuery;
use App\Services\Forecaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunForecasts extends Command
{
    protected $signature = 'surveillance:run-forecasts {--weeks=104 : Weeks of history to train on}';

    protected $description = 'Compute and store 1-4 week projections (with backtests) for the country, regions and districts';

    public function handle(AggregateQuery $q, Forecaster $forecaster): int
    {
        $latest = $q->latestWeek();
        if (! $latest) {
            $this->error('No data.');

            return self::FAILURE;
        }
        $from = $latest->subWeeks((int) $this->option('weeks') - 1);
        $units = OrgUnit::whereIn('type', ['country', 'region', 'district'])->get(['id', 'path', 'type']);
        $diseases = Disease::all();
        $done = 0;
        foreach ($diseases as $d) {
            $series = $q->seriesForUnits($units->pluck('id')->all(), $d->id, $from, $latest);
            foreach ($units as $u) {
                $s = $series[$u->id] ?? [];
                if (count($s) < 40) {
                    continue;
                }
                ksort($s);
                $res = $forecaster->forecast(array_values($s));
                if (! $res) {
                    continue;
                }
                DB::transaction(function () use ($u, $d, $res, $latest) {
                    ForecastRun::where(['org_unit_id' => $u->id, 'disease_id' => $d->id])->delete();
                    $run = ForecastRun::create(['org_unit_id' => $u->id, 'disease_id' => $d->id, 'method' => $res['method'], 'params' => $res['params'], 'trained_through' => $latest->toDateString(),
                        'horizon_weeks' => count($res['points']), 'backtest' => $res['backtest'] + ['useful' => $res['useful']]]);
                    foreach ($res['points'] as $i => $p) {
                        ForecastPoint::create(['forecast_run_id' => $run->id, 'period_start' => $latest->addWeeks($i + 1)->toDateString()] + $p);
                    }
                });
                $done++;
            }
            $this->output->write("\r".$d->name.': runs so far '.$done);
        }
        $this->newLine();
        $this->info("Forecast runs stored: $done");

        return self::SUCCESS;
    }
}
