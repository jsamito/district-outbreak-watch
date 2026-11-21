<?php

namespace App\Support;

/** Seeded random helpers for the sample-data generator (reproducible runs). */
class SampleRandom
{
    public function __construct(int $seed)
    {
        mt_srand($seed);
    }

    public function uniform(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    public function normal(float $mean = 0.0, float $sd = 1.0): float
    {
        $u = max($this->uniform(), 1e-12);
        $v = $this->uniform();

        return $mean + $sd * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }

    /** Gamma(shape, scale) by Marsaglia and Tsang. */
    public function gamma(float $shape, float $scale = 1.0): float
    {
        if ($shape < 1) {
            return $this->gamma($shape + 1, $scale) * pow(max($this->uniform(), 1e-12), 1 / $shape);
        }
        $d = $shape - 1 / 3;
        $c = 1 / sqrt(9 * $d);
        while (true) {
            do {
                $x = $this->normal();
                $v = (1 + $c * $x) ** 3;
            } while ($v <= 0);
            $u = $this->uniform();
            if (log(max($u, 1e-12)) < 0.5 * $x * $x + $d - $d * $v + $d * log($v)) {
                return $d * $v * $scale;
            }
        }
    }

    public function poisson(float $lambda): int
    {
        if ($lambda <= 0) {
            return 0;
        }
        if ($lambda > 40) {
            return max(0, (int) round($this->normal($lambda, sqrt($lambda))));
        }
        $l = exp(-$lambda);
        $k = 0;
        $p = 1.0;
        do {
            $k++;
            $p *= $this->uniform();
        } while ($p > $l);

        return $k - 1;
    }

    /** Negative binomial draw with the given mean; smaller shape means more overdispersion. */
    public function negativeBinomial(float $mean, float $shape): int
    {
        if ($mean <= 0) {
            return 0;
        }

        return $this->poisson($this->gamma($shape, $mean / $shape));
    }

    public function binomial(int $n, float $p): int
    {
        if ($n <= 0 || $p <= 0) {
            return 0;
        }
        if ($n > 60) {
            return min($n, max(0, (int) round($this->normal($n * $p, sqrt($n * $p * (1 - $p))))));
        }
        $k = 0;
        for ($i = 0; $i < $n; $i++) {
            $k += $this->uniform() < $p;
        }

        return $k;
    }
}
