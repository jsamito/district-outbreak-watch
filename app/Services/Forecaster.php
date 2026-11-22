<?php

namespace App\Services;

/**
 * Short-horizon projections of weekly counts (1 to 4 weeks ahead) with prediction intervals.
 * Models: four-week moving average, simple exponential smoothing and damped-trend smoothing.
 * Each is backtested on rolling origins against a naive "same as last week" forecast, and the one
 * with the lowest backtest error is shown, together with how it compares with naive. Intervals come
 * from in-sample one-step residuals, widen with the horizon and never go below zero.
 */
class Forecaster
{
    public const METHODS = ['naive' => 'Same as last week', 'ma4' => 'Four-week average', 'ses' => 'Exponential smoothing', 'holt' => 'Damped-trend smoothing'];
    private const Z80 = 1.2816;
    private const Z95 = 1.96;

    public function __construct(public int $horizon = 4, public int $minTrain = 26, public int $backtestOrigins = 26) {}

    /**
     * @param array<int,float> $y weekly counts, oldest first
     * @return array{method:string,label:string,params:array,points:array,backtest:array,useful:bool}|null
     */
    public function forecast(array $y): ?array
    {
        $y = array_values($y);
        if (count($y) < $this->minTrain + 8) {
            return null;
        }
        $bt = $this->backtest($y);
        $best = 'ma4';
        $bestMae = INF;
        foreach (['ma4', 'ses', 'holt'] as $m) {
            if ($bt[$m]['mae'] < $bestMae) {
                $bestMae = $bt[$m]['mae'];
                $best = $m;
            }
        }
        $fit = $this->fit($best, $y);
        $points = $this->intervals($fit['forecast'], $fit['sigma'], $best === 'ses' ? ($fit['params']['alpha'] ?? 0.3) : 0.0);
        $naiveMae = $bt['naive']['mae'];
        $skill = $naiveMae > 0 ? 1 - $bestMae / $naiveMae : 0.0;

        return [
            'method' => $best, 'label' => self::METHODS[$best], 'params' => $fit['params'], 'points' => $points,
            'useful' => $skill > 0.05,
            'backtest' => ['mae' => round($bestMae, 2), 'naive_mae' => round($naiveMae, 2), 'skill' => round($skill, 3), 'coverage80' => round($bt[$best]['coverage'], 3), 'origins' => $bt[$best]['n'],
                'by_method' => array_map(fn ($b) => round($b['mae'], 2), $bt)],
        ];
    }

    /** @return array<string,array{mae:float,coverage:float,n:int}> */
    private function backtest(array $y): array
    {
        $n = count($y);
        $first = max($this->minTrain, $n - $this->backtestOrigins - $this->horizon);
        $res = [];
        foreach (array_keys(self::METHODS) as $m) {
            $err = 0.0;
            $cnt = 0;
            $cover = 0;
            $covN = 0;
            for ($t = $first; $t <= $n - $this->horizon; $t++) {
                $train = array_slice($y, 0, $t);
                $fit = $this->fit($m, $train);
                $pts = $this->intervals($fit['forecast'], $fit['sigma'], $m === 'ses' ? ($fit['params']['alpha'] ?? 0.3) : 0.0);
                for ($h = 1; $h <= $this->horizon; $h++) {
                    $actual = $y[$t + $h - 1];
                    $err += abs($actual - $fit['forecast'][$h - 1]);
                    $cnt++;
                    $cover += ($actual >= $pts[$h - 1]['lower80'] && $actual <= $pts[$h - 1]['upper80']) ? 1 : 0;
                    $covN++;
                }
            }
            $res[$m] = ['mae' => $cnt ? $err / $cnt : INF, 'coverage' => $covN ? $cover / $covN : 0.0, 'n' => $cnt / $this->horizon];
        }

        return $res;
    }

    /** @return array{forecast:array<int,float>,sigma:float,params:array} */
    private function fit(string $method, array $y): array
    {
        return match ($method) {
            'naive' => $this->naive($y),
            'ma4' => $this->movingAverage($y),
            'ses' => $this->ses($y),
            default => $this->holt($y),
        };
    }

    private function naive(array $y): array
    {
        $last = end($y);
        $res = [];
        for ($i = 1; $i < count($y); $i++) {
            $res[] = $y[$i] - $y[$i - 1];
        }

        return ['forecast' => array_fill(0, $this->horizon, (float) $last), 'sigma' => $this->sd($res), 'params' => []];
    }

    private function movingAverage(array $y, int $k = 4): array
    {
        $res = [];
        for ($i = $k; $i < count($y); $i++) {
            $res[] = $y[$i] - array_sum(array_slice($y, $i - $k, $k)) / $k;
        }
        $mean = array_sum(array_slice($y, -$k)) / $k;

        return ['forecast' => array_fill(0, $this->horizon, $mean), 'sigma' => $this->sd($res), 'params' => ['window' => $k]];
    }

    private function ses(array $y): array
    {
        $best = null;
        foreach ([0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9] as $a) {
            $level = $y[0];
            $sse = 0.0;
            $res = [];
            for ($i = 1; $i < count($y); $i++) {
                $e = $y[$i] - $level;
                $sse += $e * $e;
                $res[] = $e;
                $level += $a * $e;
            }
            if ($best === null || $sse < $best['sse']) {
                $best = ['sse' => $sse, 'alpha' => $a, 'level' => $level, 'res' => $res];
            }
        }

        return ['forecast' => array_fill(0, $this->horizon, (float) $best['level']), 'sigma' => $this->sd($best['res']), 'params' => ['alpha' => $best['alpha']]];
    }

    private function holt(array $y): array
    {
        $phi = 0.9;
        $best = null;
        foreach ([0.2, 0.4, 0.6, 0.8] as $a) {
            foreach ([0.1, 0.2, 0.4] as $b) {
                $l = $y[0];
                $t = $y[1] - $y[0];
                $sse = 0.0;
                $res = [];
                for ($i = 1; $i < count($y); $i++) {
                    $pred = $l + $phi * $t;
                    $e = $y[$i] - $pred;
                    $sse += $e * $e;
                    $res[] = $e;
                    $lNew = $pred + $a * $e;
                    $t = $phi * $t + $b * $a * $e;
                    $l = $lNew;
                }
                if ($best === null || $sse < $best['sse']) {
                    $best = ['sse' => $sse, 'a' => $a, 'b' => $b, 'l' => $l, 't' => $t, 'res' => $res];
                }
            }
        }
        $f = [];
        $acc = 0.0;
        for ($h = 1; $h <= $this->horizon; $h++) {
            $acc += $phi ** $h;
            $f[] = $best['l'] + $acc * $best['t'];
        }

        return ['forecast' => $f, 'sigma' => $this->sd($best['res']), 'params' => ['alpha' => $best['a'], 'beta' => $best['b'], 'phi' => $phi]];
    }

    private function sd(array $r): float
    {
        $n = count($r);

        return $n > 1 ? sqrt(array_sum(array_map(fn ($x) => $x * $x, $r)) / ($n - 1)) : 0.0;
    }

    /** @return array<int,array{point:float,lower80:float,upper80:float,lower95:float,upper95:float}> */
    private function intervals(array $forecast, float $sigma, float $alpha): array
    {
        $out = [];
        foreach ($forecast as $i => $f) {
            $h = $i + 1;
            $f = max(0.0, $f);
            $s = $sigma * sqrt(1 + ($h - 1) * ($alpha > 0 ? $alpha * $alpha : 0.15));
            $s = max($s, sqrt($f)); // counts are at least Poisson-noisy
            $out[] = [
                'point' => round($f, 2), 'lower80' => round(max(0, $f - self::Z80 * $s), 2), 'upper80' => round($f + self::Z80 * $s, 2),
                'lower95' => round(max(0, $f - self::Z95 * $s), 2), 'upper95' => round($f + self::Z95 * $s, 2),
            ];
        }

        return $out;
    }
}
