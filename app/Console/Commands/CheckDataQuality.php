<?php

namespace App\Console\Commands;

use App\Services\DataQualityChecker;
use Illuminate\Console\Command;

class CheckDataQuality extends Command
{
    protected $signature = 'surveillance:check-quality';

    protected $description = 'Look for missing reports, impossible values and duplicates, and update the data-quality flags';

    public function handle(DataQualityChecker $checker): int
    {
        $s = $checker->run();
        $this->info("Found {$s['found']} problems: {$s['created']} new, {$s['reopened']} reopened, {$s['closed']} closed. {$s['open']} open now.");

        return self::SUCCESS;
    }
}
