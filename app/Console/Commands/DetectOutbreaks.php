<?php

namespace App\Console\Commands;

use App\Services\AlertEngine;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class DetectOutbreaks extends Command
{
    protected $signature = 'surveillance:detect-outbreaks {--week= : Week start to evaluate (default: latest week with data)} {--no-notify : Create alerts without sending email}';

    protected $description = 'Evaluate alert rules against the latest weekly data, create or update alerts and notify the right people';

    public function handle(AlertEngine $engine): int
    {
        $week = $this->option('week') ? CarbonImmutable::parse($this->option('week'))->startOfWeek() : null;
        $s = $engine->run($week, ! $this->option('no-notify'));
        $this->info("Alerts created: {$s['created']}, updated: {$s['updated']}, resolved: {$s['resolved']}, immediate emails: {$s['notified']}");

        return self::SUCCESS;
    }
}
