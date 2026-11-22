<?php

namespace Tests\Unit;

use App\Services\ControlChartService;
use App\Services\Forecaster;
use App\Services\OutbreakDetector;
use App\Support\SampleRandom;
use PHPUnit\Framework\TestCase;

class AnalyticsServicesTest extends TestCase
{
    private function weeks(array $values): array
    {
        $out = [];
        foreach ($values as $i => $v) {
            $out[date('Y-m-d', strtotime('2026-01-05 +'.$i.' weeks'))] = (float) $v;
        }

        return $out;
    }

    public function test_detector_uses_the_baseline_ending_two_weeks_before_and_the_minimum_count(): void
    {
        $series = $this->weeks([...array_fill(0, 10, 4), 20]); // flat 4, then 20
        $res = (new OutbreakDetector)->evaluate($series);
        $last = array_key_last($series);

        $this->assertEquals(4.0, $res[$last]['expected']);
        $this->assertEquals(10.0, $res[$last]['upper'], '4 + 3 * sqrt(4)');
        $this->assertTrue($res[$last]['signal']);

        $small = $this->weeks([...array_fill(0, 10, 0), 4]);
        $this->assertFalse((new OutbreakDetector)->evaluate($small)[array_key_last($small)]['signal'], 'below the minimum count of 5');

        $growing = $this->weeks([4, 4, 4, 4, 4, 4, 4, 4, 4, 6, 8]);
        $r = (new OutbreakDetector)->evaluate($growing);
        $this->assertEquals(4.0, $r[array_key_last($growing)]['expected'], 'the guard week keeps a growing outbreak out of its own baseline');
    }

    public function test_detector_does_not_evaluate_weeks_without_enough_history(): void
    {
        $this->assertSame([], (new OutbreakDetector)->evaluate($this->weeks([1, 2, 3, 4, 5])));
    }

    public function test_severity_tiers(): void
    {
        $this->assertSame('high', OutbreakDetector::severity(30, 10));
        $this->assertSame('medium', OutbreakDetector::severity(20, 10));
        $this->assertSame('low', OutbreakDetector::severity(12, 10));
        $this->assertSame('high', OutbreakDetector::severity(1, 0, true));
    }

    public function test_control_chart_limits_follow_the_u_chart_formula(): void
    {
        $counts = $this->weeks(array_fill(0, 40, 10));
        $pop = array_map(fn () => 100000.0, $counts);
        $counts[array_key_last($counts)] = 60.0;
        $r = (new ControlChartService)->build($counts, $pop);

        $centre = 10 / 100000;
        $this->assertEqualsWithDelta($centre * 100000, $r['centre'], 1e-6);
        $last = end($r['points']);
        $this->assertEqualsWithDelta(($centre + 3 * sqrt($centre / 100000)) * 100000, $last['ucl'], 1e-6);
        $this->assertTrue($last['above_ucl']);
        $this->assertFalse($r['points'][5]['above_ucl']);
        $this->assertGreaterThanOrEqual(0, $last['lcl']);
    }

    public function test_control_chart_baseline_excludes_the_latest_weeks_and_flags_a_sustained_shift(): void
    {
        $counts = $this->weeks([...array_fill(0, 40, 10), ...array_fill(0, 12, 14)]);
        $pop = array_map(fn () => 1000000.0, $counts);
        $r = (new ControlChartService)->build($counts, $pop);

        $this->assertEqualsWithDelta(1.0, $r['centre'], 1e-6, 'the 12 raised weeks are held out of the baseline');
        $this->assertTrue(end($r['points'])['shift'], 'eight consecutive weeks above the centre line');
    }

    public function test_forecast_shape_non_negative_intervals_that_widen_and_reasonable_level(): void
    {
        mt_srand(7);
        $y = [];
        for ($i = 0; $i < 70; $i++) {
            $y[] = max(0, 50 + 10 * sin($i / 8) + random_int(-4, 4));
        }
        $f = (new Forecaster)->forecast($y);

        $this->assertNotNull($f);
        $this->assertCount(4, $f['points']);
        foreach ($f['points'] as $p) {
            $this->assertGreaterThanOrEqual(0, $p['lower95']);
            $this->assertTrue($p['lower95'] <= $p['lower80'] && $p['lower80'] <= $p['point'] && $p['point'] <= $p['upper80'] && $p['upper80'] <= $p['upper95'], 'nested intervals around the point forecast');
            $this->assertGreaterThan(30, $p['point']);
            $this->assertLessThan(75, $p['point']);
        }
        $w1 = $f['points'][0]['upper80'] - $f['points'][0]['lower80'];
        $w4 = $f['points'][3]['upper80'] - $f['points'][3]['lower80'];
        $this->assertGreaterThan(0.9 * $w1, $w4, 'uncertainty does not shrink with the horizon');
        $this->assertArrayHasKey('naive', $f['backtest']['by_method']);
        $this->assertGreaterThan(0, $f['backtest']['origins']);
        $this->assertContains($f['method'], ['ma4', 'ses', 'holt']);
    }

    public function test_forecast_refuses_short_series_and_never_goes_below_zero(): void
    {
        $this->assertNull((new Forecaster)->forecast(array_fill(0, 20, 5)));
        $f = (new Forecaster)->forecast(array_merge(array_fill(0, 50, 0), [1, 0, 0, 2, 0, 0, 0, 1, 0, 0]));
        foreach ($f['points'] as $p) {
            $this->assertGreaterThanOrEqual(0, $p['lower80']);
            $this->assertGreaterThanOrEqual(0, $p['point']);
        }
    }

    public function test_forecast_reports_honestly_whether_it_beats_the_naive_baseline(): void
    {
        mt_srand(3);
        $walk = [200.0];
        for ($i = 1; $i < 90; $i++) {
            $walk[] = max(0, $walk[$i - 1] + random_int(-30, 30));
        }
        $f = (new Forecaster)->forecast($walk);

        $this->assertSame($f['backtest']['skill'] > 0.05, $f['useful'], 'the flag follows the measured improvement over naive');
        $this->assertLessThan(0.15, $f['backtest']['skill'], 'a random walk is hard to beat by more than a little');
        $this->assertGreaterThan(0, $f['backtest']['naive_mae']);
    }

    public function test_sample_random_is_reproducible_and_statistically_sensible(): void
    {
        $a = new SampleRandom(11);
        $x = array_map(fn () => $a->negativeBinomial(20, 10), range(1, 2000));
        $b = new SampleRandom(11);
        $y = array_map(fn () => $b->negativeBinomial(20, 10), range(1, 2000));
        $this->assertSame($x, $y);
        $this->assertEqualsWithDelta(20, array_sum($x) / 2000, 1.0);
        $this->assertGreaterThan(20, (function ($v) { $m = array_sum($v) / count($v); return array_sum(array_map(fn ($z) => ($z - $m) ** 2, $v)) / count($v); })($x), 'overdispersed: variance above the mean');
    }
}
