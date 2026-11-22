<?php

namespace App\Services;

/**
 * Short-baseline outbreak signal. A week signals when its count exceeds the baseline mean plus three
 * Poisson standard deviations and reaches a minimum count. The baseline is the eight weeks ending two
 * weeks before the week tested, so a growing outbreak does not hide itself.
 */
class OutbreakDetector
{
    public function __construct(
        public int $baselineWeeks = 8,
        public int $guardWeeks = 1,
        public float $sigmas = 3.0,
        public int $minCount = 5,
        public int $minBaselineWeeks = 5,
    ) {}

    /**
     * @param array<string,float> $series week_start => value
     * @return array<string,array{observed:float,expected:float,upper:float,signal:bool}>
     */
    public function evaluate(array $series): array
    {
        ksort($series);
        $weeks = array_keys($series);
        $out = [];
        foreach ($weeks as $i => $week) {
            $end = $i - 1 - $this->guardWeeks;
            $start = $end - $this->baselineWeeks + 1;
            if ($start < 0) {
                continue;
            }
            $base = [];
            for ($j = $start; $j <= $end; $j++) {
                $base[] = $series[$weeks[$j]];
            }
            if (count($base) < $this->minBaselineWeeks) {
                continue;
            }
            $mean = array_sum($base) / count($base);
            $upper = $mean + $this->sigmas * sqrt(max($mean, 0.25));
            $observed = $series[$week];
            $out[$week] = ['observed' => $observed, 'expected' => round($mean, 2), 'upper' => round($upper, 2), 'signal' => $observed >= $this->minCount && $observed > $upper];
        }

        return $out;
    }

    public static function severity(float $observed, float $expected, bool $immediate = false): string
    {
        if ($immediate) {
            return 'high';
        }
        $ratio = $observed / max($expected, 1);

        return $ratio >= 3 ? 'high' : ($ratio >= 2 ? 'medium' : 'low');
    }
}
