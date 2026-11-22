<?php

namespace App\Services;

use App\Models\Disease;
use App\Models\Indicator;
use App\Models\OrgUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Finds areas whose latest week crosses the outbreak signal threshold, limited to what a user may see. */
class SignalService
{
    public function __construct(private OutbreakDetector $detector, private AggregateQuery $query) {}

    /** @return Collection<int,object{unit_id:int,unit:string,type:string,disease_id:int,disease:string,immediate:bool,week:string,observed:float,expected:float,upper:float,severity:string}> */
    public function current(User $user, ?CarbonImmutable $week = null): Collection
    {
        $week ??= $this->query->latestWeek();
        if (! $week) {
            return collect();
        }
        $units = $user->scopedOrgUnits()->where('type', OrgUnit::DISTRICT)->get(['id', 'name', 'type']);
        if ($units->isEmpty()) {
            $units = $user->scopedOrgUnits()->where('type', OrgUnit::FACILITY)->get(['id', 'name', 'type']);
        }
        if ($units->isEmpty()) {
            return collect();
        }

        return $this->forUnits($units, $week);
    }

    /** @param Collection<int,object> $units objects with id, name, type */
    public function forUnits(Collection $units, CarbonImmutable $week): Collection
    {
        $diseases = Disease::all()->keyBy('id');
        $from = $week->subWeeks($this->detector->baselineWeeks + $this->detector->guardWeeks + 2);
        $rows = DB::table('indicator_values')
            ->where(['indicator_id' => Indicator::idFor(Indicator::CASES), 'period_type' => 'week'])
            ->whereIn('org_unit_id', $units->pluck('id'))->whereBetween('period_start', [$from->toDateString(), $week->toDateString()])
            ->get(['org_unit_id', 'dimension_id', 'period_start', 'value']);
        $series = [];
        foreach ($rows as $r) {
            $series[$r->org_unit_id][$r->dimension_id][$r->period_start] = (float) $r->value;
        }

        $out = collect();
        foreach ($units as $u) {
            foreach ($series[$u->id] ?? [] as $diseaseId => $s) {
                $d = $diseases[$diseaseId] ?? null;
                if (! $d || ! isset($s[$week->toDateString()])) {
                    continue;
                }
                $observed = $s[$week->toDateString()];
                if ($d->immediate_notifiable) {
                    if ($observed >= 1) {
                        $out->push($this->row($u, $d, $week, $observed, 0, 0, true));
                    }

                    continue;
                }
                $res = $this->detector->evaluate($s)[$week->toDateString()] ?? null;
                if ($res && $res['signal']) {
                    $out->push($this->row($u, $d, $week, $observed, $res['expected'], $res['upper'], false));
                }
            }
        }
        $rank = ['high' => 0, 'medium' => 1, 'low' => 2];

        return $out->sortBy(fn ($r) => [$rank[$r->severity], -($r->observed / max($r->expected, 1))])->values();
    }

    private function row($u, Disease $d, CarbonImmutable $week, float $observed, float $expected, float $upper, bool $immediate): object
    {
        return (object) [
            'unit_id' => $u->id, 'unit' => $u->name, 'type' => $u->type, 'disease_id' => $d->id, 'disease' => $d->name, 'immediate' => $immediate,
            'week' => $week->toDateString(), 'observed' => $observed, 'expected' => $expected, 'upper' => $upper,
            'severity' => OutbreakDetector::severity($observed, $expected, $immediate),
        ];
    }
}
