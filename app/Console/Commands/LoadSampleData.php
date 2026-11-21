<?php

namespace App\Console\Commands;

use App\Models\Antigen;
use App\Models\DataSource;
use App\Models\Disease;
use App\Models\ImportBatch;
use App\Models\OrgUnit;
use App\Models\ReferenceEstimate;
use App\Services\IndicatorEngine;
use App\Support\SampleRandom;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LoadSampleData extends Command
{
    protected $signature = 'surveillance:load-sample-data {--seed=20261122 : Random seed for reproducible data} {--weeks= : Weeks of history (default from config)}';

    protected $description = 'Generate weekly facility reports, reporting statuses and immunization doses from real district populations, then rebuild indicators';

    private const TYPE_WEIGHT = ['hospital' => 3.0, 'hc4' => 2.0, 'hc3' => 1.2, 'hc2' => 0.8, 'clinic' => 0.5, 'other' => 0.5];
    private const TYPE_REPORTING = ['hospital' => 0.97, 'hc4' => 0.94, 'hc3' => 0.9, 'hc2' => 0.82, 'clinic' => 0.8, 'other' => 0.75];

    public function handle(IndicatorEngine $engine): int
    {
        ini_set('memory_limit', '1G');
        $rng = new SampleRandom((int) $this->option('seed'));
        $weeksN = (int) ($this->option('weeks') ?: config('surveillance.history_weeks'));
        $source = DataSource::where('code', 'sample-weekly-reports')->first();
        $batch = ImportBatch::begin('surveillance:load-sample-data', $source);

        $lastWeek = CarbonImmutable::today()->startOfWeek()->subWeek();
        $weeks = [];
        for ($i = $weeksN - 1; $i >= 0; $i--) {
            $weeks[] = $lastWeek->subWeeks($i);
        }

        $diseases = Disease::pluck('id', 'code');
        $facilities = $this->facilities();
        if ($facilities->isEmpty()) {
            $this->error('No sentinel facilities found. Run surveillance:import-geography first.');

            return self::FAILURE;
        }
        $this->info('Sentinel facilities: '.$facilities->count().' | weeks: '.$weeksN);

        DB::table('facility_reports')->where('origin', 'synthetic')->delete();
        DB::table('reporting_statuses')->delete();
        DB::table('immunization_doses')->where('origin', 'synthetic')->delete();

        $scenarios = $this->scenarios($lastWeek);
        $districtModifier = [];
        $reportRows = [];
        $statusRows = [];
        $total = 0;

        foreach ($facilities as $f) {
            $districtModifier[$f->district_id] ??= $rng->uniform() < 0.08 ? 0.6 + 0.15 * $rng->uniform() : 0.88 + 0.12 * $rng->uniform();
            $propensity = min(0.995, self::TYPE_REPORTING[$f->facility_type] * $districtModifier[$f->district_id]);
            $firstInDistrict = $facilities->where('district_id', $f->district_id)->first()->id === $f->id;

            foreach ($weeks as $wi => $week) {
                $reported = $rng->uniform() < $propensity;
                $statusRows[] = [
                    'org_unit_id' => $f->id, 'week_start' => $week->toDateString(), 'received' => $reported,
                    'on_time' => $reported && $rng->uniform() < 0.86,
                    'received_at' => $reported ? $week->addDays(7 + ($rng->uniform() < 0.86 ? 2 : 5))->setTime(10, 0) : null,
                ];
                if (! $reported) {
                    continue;
                }
                $monthFrac = $week->addDays(3)->month + ($week->addDays(3)->day - 1) / 30;
                foreach (config('surveillance.diseases') as $code => $m) {
                    $mean = $f->catchment / 1000 * $m['rate'];
                    $mean *= 1 + $m['amplitude'] * cos(2 * M_PI * ($monthFrac - $m['peak_month']) / 12);
                    $cases = null;
                    foreach ($scenarios as $s) {
                        if ($s['disease'] !== $code || $s['district_id'] !== $f->district_id) {
                            continue;
                        }
                        $t = $wi - $s['start_index'];
                        if ($t < 0 || $t >= $s['duration']) {
                            continue;
                        }
                        if ($s['single_case']) {
                            $cases = $firstInDistrict ? 1 : 0;

                            continue;
                        }
                        $p = sin(M_PI * ($t + 0.5) / $s['duration']);
                        $mean = $mean * (1 + ($s['multiplier'] - 1) * $p) + $s['extra_peak'] * $p * (self::TYPE_WEIGHT[$f->facility_type] / 1.2);
                    }
                    $cases ??= $rng->negativeBinomial($mean, (float) $m['overdispersion']);
                    $deaths = $rng->binomial($cases, (float) $m['cfr']);
                    $reportRows[] = [
                        'org_unit_id' => $f->id, 'disease_id' => $diseases[$code], 'week_start' => $week->toDateString(),
                        'cases' => $cases, 'deaths' => $deaths, 'origin' => 'synthetic', 'import_batch_id' => $batch->id,
                    ];
                }
                if (count($reportRows) >= 4000) {
                    $total += $this->flush('facility_reports', $reportRows, ['org_unit_id', 'disease_id', 'week_start'], ['cases', 'deaths', 'origin', 'import_batch_id']);
                }
                if (count($statusRows) >= 4000) {
                    $this->flush('reporting_statuses', $statusRows, ['org_unit_id', 'week_start'], ['received', 'on_time', 'received_at']);
                }
            }
        }
        $total += $this->flush('facility_reports', $reportRows, ['org_unit_id', 'disease_id', 'week_start'], ['cases', 'deaths', 'origin', 'import_batch_id']);
        $this->flush('reporting_statuses', $statusRows, ['org_unit_id', 'week_start'], ['received', 'on_time', 'received_at']);
        $this->info('Facility report rows: '.number_format($total));

        $doses = $this->immunization($rng, $batch->id);
        $this->info('Immunization rows: '.number_format($doses));

        $batch->finish($total + $doses, 0, 'seed '.$this->option('seed'));

        foreach ($engine->rebuildAll() as $code => $rows) {
            $this->info(str_pad($code, 26).number_format($rows).' indicator rows');
        }

        return self::SUCCESS;
    }

    private function facilities()
    {
        $year = (int) now()->year;
        $pop = DB::table('populations')->where('year', $year)->pluck('total', 'org_unit_id');
        $weights = DB::table('org_units as o')->join('facility_profiles as p', 'p.org_unit_id', '=', 'o.id')
            ->select('o.parent_id', 'p.facility_type')->get()
            ->groupBy('parent_id')->map(fn ($g) => $g->sum(fn ($r) => self::TYPE_WEIGHT[$r->facility_type] ?? 0.5));

        return DB::table('org_units as o')->join('facility_profiles as p', 'p.org_unit_id', '=', 'o.id')
            ->where('p.sentinel', true)->orderBy('o.parent_id')->orderBy('o.id')
            ->get(['o.id', 'o.parent_id as district_id', 'p.facility_type'])
            ->map(function ($r) use ($pop, $weights) {
                $total = max($weights[$r->district_id] ?? 1, 1);
                $r->catchment = ($pop[$r->district_id] ?? 0) * (self::TYPE_WEIGHT[$r->facility_type] ?? 0.5) / $total;

                return $r;
            })->values();
    }

    private function scenarios(CarbonImmutable $lastWeek): array
    {
        $out = [];
        foreach (config('surveillance.scenarios') as $s) {
            $district = OrgUnit::where('type', 'district')->where('name', $s['district'])->first();
            if (! $district) {
                continue;
            }
            $weeksN = (int) ($this->option('weeks') ?: config('surveillance.history_weeks'));
            $out[] = [
                'disease' => $s['disease'], 'district_id' => $district->id,
                'start_index' => $weeksN - 1 - ($s['start_weeks_ago'] - 1) - ($s['duration'] - 1) + ($s['duration'] - 1) - ($s['duration'] - 1),
                'duration' => $s['duration'], 'multiplier' => $s['multiplier'], 'extra_peak' => $s['extra_peak'], 'single_case' => $s['single_case'] ?? false,
            ];
            // start_index counts from the oldest week: the scenario begins `start_weeks_ago` weeks before the last week.
            $out[count($out) - 1]['start_index'] = max(0, $weeksN - $s['start_weeks_ago']);
        }

        return $out;
    }

    private function immunization(SampleRandom $rng, int $batchId): int
    {
        $antigens = Antigen::all();
        $estimates = ReferenceEstimate::all()->groupBy('antigen_id');
        $districts = OrgUnit::where('type', 'district')->get(['id']);
        $pop = DB::table('populations')->get(['org_unit_id', 'year', 'under1'])->groupBy('org_unit_id');
        $months = (int) config('surveillance.vaccination_months');
        $lastMonth = CarbonImmutable::today()->startOfMonth()->subMonth();
        $rows = [];
        $n = 0;
        foreach ($districts as $d) {
            $byYear = $pop[$d->id]->keyBy('year');
            foreach ($antigens as $a) {
                $effect = $rng->normal(0, 7);
                for ($i = $months - 1; $i >= 0; $i--) {
                    $month = $lastMonth->subMonths($i);
                    $est = $estimates[$a->id] ?? collect();
                    $who = $est->firstWhere('year', $month->year) ?? $est->sortByDesc('year')->first();
                    $base = $who ? (float) $who->coverage_percent : 85.0;
                    $season = in_array($month->month, [12, 1]) ? -9 : (in_array($month->month, [6, 7]) ? -3 : 0);
                    $cov = max(20, min(130, $base + $effect + $season + $rng->normal(0, 4)));
                    $target = (int) round(($byYear[$month->year]->under1 ?? $byYear->last()->under1) / 12);
                    $rows[] = ['org_unit_id' => $d->id, 'antigen_id' => $a->id, 'month' => $month->toDateString(), 'doses' => (int) round($target * $cov / 100),
                        'target' => $target, 'origin' => 'synthetic', 'import_batch_id' => $batchId];
                    $n++;
                    if (count($rows) >= 2000) {
                        $this->flush('immunization_doses', $rows, ['org_unit_id', 'antigen_id', 'month'], ['doses', 'target', 'origin', 'import_batch_id']);
                    }
                }
            }
        }
        $this->flush('immunization_doses', $rows, ['org_unit_id', 'antigen_id', 'month'], ['doses', 'target', 'origin', 'import_batch_id']);

        return $n;
    }

    private function flush(string $table, array &$rows, array $keys, array $update): int
    {
        $n = count($rows);
        foreach (array_chunk($rows, 2000) as $chunk) {
            DB::table($table)->upsert($chunk, $keys, $update);
        }
        $rows = [];

        return $n;
    }
}
