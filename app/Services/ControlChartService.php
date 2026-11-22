<?php

namespace App\Services;

/**
 * u-chart for counts with a varying population: u = c / n, centre = sum(c) / sum(n),
 * limits = centre +/- 3 * sqrt(centre / n_i). Limits come from a baseline window that excludes the
 * most recent weeks so an outbreak cannot widen its own limits. Rates are returned per 100,000.
 */
class ControlChartService
{
    public const PER = 100000;

    /**
     * @param array<string,float> $counts week => cases
     * @param array<string,float> $population week => population (same keys)
     * @return array{points:array<int,array>,centre:float,baseline_weeks:int,rule:string}
     */
    public function build(array $counts, array $population, int $baselineWeeks = 52, int $holdOutWeeks = 12): array
    {
        ksort($counts);
        $weeks = array_keys($counts);
        $n = count($weeks);
        $baseEnd = max(0, $n - $holdOutWeeks);
        $baseStart = max(0, $baseEnd - $baselineWeeks);
        $sumC = $sumN = 0.0;
        for ($i = $baseStart; $i < $baseEnd; $i++) {
            $sumC += $counts[$weeks[$i]];
            $sumN += $population[$weeks[$i]] ?? 0;
        }
        $centre = $sumN > 0 ? $sumC / $sumN : 0.0;

        $points = [];
        $run = 0;
        foreach ($weeks as $i => $w) {
            $pop = (float) ($population[$w] ?? 0);
            $u = $pop > 0 ? $counts[$w] / $pop : null;
            $sd = $pop > 0 ? 3 * sqrt($centre / $pop) : null;
            $ucl = $sd !== null ? $centre + $sd : null;
            $lcl = $sd !== null ? max(0, $centre - $sd) : null;
            $run = ($u !== null && $u > $centre) ? $run + 1 : 0;
            $points[] = [
                'week' => $w, 'cases' => $counts[$w],
                'rate' => $u !== null ? $u * self::PER : null,
                'ucl' => $ucl !== null ? $ucl * self::PER : null,
                'lcl' => $lcl !== null ? $lcl * self::PER : null,
                'above_ucl' => $u !== null && $ucl !== null && $u > $ucl && $counts[$w] >= (int) config('surveillance.small_number_floor'),
                'shift' => $run >= 8,
            ];
        }

        return ['points' => $points, 'centre' => $centre * self::PER, 'baseline_weeks' => $baseEnd - $baseStart,
            'rule' => 'Limits are centre ± 3 standard errors from the baseline weeks; a point above the upper limit, or eight consecutive weeks above the centre line, is flagged.'];
    }
}
