<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Services\AlertDispatcher;
use Illuminate\Console\Command;

class EscalateAlerts extends Command
{
    protected $signature = 'surveillance:escalate-alerts';

    protected $description = 'Escalate alerts that have not been acknowledged within their rule\'s time limit';

    public function handle(AlertDispatcher $dispatcher): int
    {
        $n = 0;
        foreach (Alert::whereIn('status', ['open'])->where('escalation_level', 0)->whereHas('rule', fn ($q) => $q->whereNotNull('escalate_after_minutes'))->with('rule')->get() as $alert) {
            if ($alert->triggered_at->addMinutes($alert->rule->escalate_after_minutes)->isPast()) {
                $dispatcher->escalate($alert);
                $n++;
            }
        }
        $this->info("Escalated: $n");

        return self::SUCCESS;
    }
}
