<?php

namespace App\Console\Commands;

use App\Services\SyntheticCaseGenerator;
use Illuminate\Console\Command;

class GenerateCases extends Command
{
    protected $signature = 'surveillance:generate-cases {--weeks=26 : Recent weeks to expand into case records} {--seed=20261122}';

    protected $description = 'Expand the weekly facility reports of case-reported diseases into synthetic case records (replaces existing synthetic cases)';

    public function handle(SyntheticCaseGenerator $generator): int
    {
        ini_set('memory_limit', '1G');
        $n = $generator->generate((int) $this->option('weeks'), (int) $this->option('seed'), fn ($done) => $this->output->write("\r".number_format($done).' cases'));
        $this->newLine();
        $this->info('Synthetic cases generated: '.number_format($n));

        return self::SUCCESS;
    }
}
