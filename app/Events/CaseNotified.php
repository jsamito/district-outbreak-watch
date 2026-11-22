<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Tells open dashboards that a notification arrived. Carries counts and areas only, never case-level data. */
class CaseNotified implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param array<int,int> $areaIds area ids (facility's district, region and country) that should hear about it */
    public function __construct(public array $areaIds, public string $disease, public string $area, public string $week, public int $districtCases) {}

    public function broadcastOn(): array
    {
        return array_map(fn ($id) => new PrivateChannel('area.'.$id), $this->areaIds);
    }

    public function broadcastAs(): string
    {
        return 'case.notified';
    }

    public function broadcastWith(): array
    {
        return ['disease' => $this->disease, 'area' => $this->area, 'week' => $this->week, 'district_cases' => $this->districtCases];
    }
}
