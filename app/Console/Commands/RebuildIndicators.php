<?php

namespace App\Console\Commands;

use App\Services\IndicatorEngine;
use Illuminate\Console\Command;

class RebuildIndicators extends Command
{
    protected $signature = 'surveillance:rebuild-indicators';

    protected $description = 'Recompute stored indicators for every area level from the facility reports, reporting statuses and immunization doses';

    public function handle(IndicatorEngine $engine): int
    {
        foreach ($engine->rebuildAll() as $code => $rows) {
            $this->info(str_pad($code, 26).number_format($rows).' rows');
        }

        return self::SUCCESS;
    }
}
