<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Disease;
use App\Models\Indicator;
use App\Models\OrgUnit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Turns signals into alerts: one alert per area and disease while a signal lasts (no repeat alerts for
 * each further week), reopening a recently resolved alert instead of creating a new one (cooldown).
 */
class AlertEngine
{
    private const RANK = ['low' => 1, 'medium' => 2, 'high' => 3];

    public function __construct(private AggregateQuery $query, private AlertDispatcher $dispatcher) {}

    /** @return array{created:int,updated:int,resolved:int,notified:int} */
    public function run(?CarbonImmutable $week = null, bool $notify = true, ?array $onlyUnitIds = null): array
    {
        $week ??= $this->query->latestWeek();
        $stats = ['created' => 0, 'updated' => 0, 'resolved' => 0, 'notified' => 0];
        if (! $week) {
            return $stats;
        }
        foreach (AlertRule::where('enabled', true)->get() as $rule) {
            $this->runRule($rule, $week, $notify, $stats, $onlyUnitIds);
        }

        return $stats;
    }

    private function runRule(AlertRule $rule, CarbonImmutable $week, bool $notify, array &$stats, ?array $onlyUnitIds = null): void
    {
        $params = $rule->params ?? [];
        $detector = new OutbreakDetector(
            baselineWeeks: (int) ($params['baseline_weeks'] ?? 8), guardWeeks: (int) ($params['guard_weeks'] ?? 1),
            sigmas: (float) ($params['sigmas'] ?? 3.0), minCount: (int) ($rule->min_count ?: 1)
        );
        $single = $rule->method === 'single_case';

        $diseases = $rule->disease_id ? Disease::where('id', $rule->disease_id)->get()
            : Disease::where('immediate_notifiable', $single)->get();
        $units = $rule->org_unit_id
            ? OrgUnit::within($rule->orgUnit)->where('type', OrgUnit::DISTRICT)->get(['id', 'name'])
            : OrgUnit::where('type', OrgUnit::DISTRICT)->get(['id', 'name']);
        if ($onlyUnitIds !== null) {
            $units = $units->whereIn('id', $onlyUnitIds)->values();
        }
        if ($diseases->isEmpty() || $units->isEmpty()) {
            return;
        }

        $from = $week->subWeeks($detector->baselineWeeks + $detector->guardWeeks + 3);
        $rows = DB::table('indicator_values')
            ->where(['indicator_id' => Indicator::idFor(Indicator::CASES), 'period_type' => 'week'])
            ->whereIn('dimension_id', $diseases->pluck('id'))->whereIn('org_unit_id', $units->pluck('id'))
            ->whereBetween('period_start', [$from->toDateString(), $week->toDateString()])
            ->get(['org_unit_id', 'dimension_id', 'period_start', 'value']);
        $series = [];
        foreach ($rows as $r) {
            $series[$r->org_unit_id][$r->dimension_id][$r->period_start] = (float) $r->value;
        }

        $cooldown = now()->subHours((int) $rule->cooldown_hours);
        foreach ($units as $u) {
            foreach ($diseases as $d) {
                $s = $series[$u->id][$d->id] ?? [];
                $key = $week->toDateString();
                $observed = $s[$key] ?? null;
                $signal = false;
                $expected = 0.0;
                $upper = 0.0;
                if ($single) {
                    $signal = $observed !== null && $observed >= max(1, $rule->min_count);
                } elseif ($observed !== null) {
                    $res = $detector->evaluate($s)[$key] ?? null;
                    $signal = $res && $res['signal'];
                    $expected = $res['expected'] ?? 0;
                    $upper = $res['upper'] ?? 0;
                }

                $active = Alert::where(['alert_rule_id' => $rule->id, 'org_unit_id' => $u->id, 'disease_id' => $d->id])->active()->first();
                if ($signal) {
                    $severity = $rule->severity === 'auto' ? OutbreakDetector::severity((float) $observed, $expected, $single) : $rule->severity;
                    if ($active) {
                        $raised = self::RANK[$severity] > self::RANK[$active->severity];
                        if ($active->last_signal_week->lt($week)) {
                            $active->signal_weeks++;
                        }
                        $active->fill(['last_signal_week' => $week, 'observed' => $observed, 'expected' => $expected, 'upper_limit' => $upper, 'severity' => $raised ? $severity : $active->severity])->save();
                        $stats['updated']++;
                        if ($raised && $notify) {
                            $stats['notified'] += $this->dispatcher->notifyNew($active);
                        }

                        continue;
                    }
                    $recent = Alert::where(['alert_rule_id' => $rule->id, 'org_unit_id' => $u->id, 'disease_id' => $d->id, 'status' => 'resolved'])
                        ->where('resolved_at', '>=', $cooldown)->latest('resolved_at')->first();
                    if ($recent) {
                        $recent->fill(['status' => 'open', 'resolved_at' => null, 'last_signal_week' => $week, 'signal_weeks' => $recent->signal_weeks + 1,
                            'observed' => $observed, 'expected' => $expected, 'upper_limit' => $upper, 'severity' => $severity, 'acknowledged_at' => null, 'acknowledged_by' => null])->save();
                        $stats['updated']++;

                        continue;
                    }
                    $alert = Alert::create([
                        'alert_rule_id' => $rule->id, 'org_unit_id' => $u->id, 'disease_id' => $d->id, 'period_start' => $week, 'last_signal_week' => $week,
                        'observed' => $observed, 'expected' => $expected, 'upper_limit' => $upper, 'severity' => $severity, 'status' => 'open', 'triggered_at' => now(),
                    ]);
                    $stats['created']++;
                    if ($notify) {
                        $stats['notified'] += $this->dispatcher->notifyNew($alert);
                    }
                } elseif ($active && $active->last_signal_week->lte($week->subWeeks(2))) {
                    $active->update(['status' => 'resolved', 'resolved_at' => now()]);
                    $stats['resolved']++;
                }
            }
        }
    }
}
