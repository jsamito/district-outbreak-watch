<?php

namespace App\Console\Commands;

use Database\Seeders\SampleUsersSeeder;
use Illuminate\Console\Command;

class InstallSample extends Command
{
    protected $signature = 'surveillance:install-sample {--no-users : Do not create the sample role accounts} {--production-users : Create the live administrator and Lira accounts (ProductionUsersSeeder)} {--force : Required in production}';

    protected $description = 'Build a complete working system from scratch: database, Uganda geography, reference data, sample series, cases, projections and alerts';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Refusing to wipe a production database without --force.');

            return self::FAILURE;
        }
        $steps = [
            ['migrate:fresh', ['--seed' => true, '--force' => true]],
            ['surveillance:import-geography', []],
            ['surveillance:import-reference', []],
            ['surveillance:load-sample-data', []],
            ['surveillance:generate-cases', []],
            ['surveillance:run-forecasts', []],
            ['surveillance:check-quality', []],
            ['surveillance:detect-outbreaks', ['--no-notify' => true]],
        ];
        foreach ($steps as [$cmd, $args]) {
            $this->components->info($cmd);
            if ($this->call($cmd, $args) !== 0) {
                return self::FAILURE;
            }
        }
        if (! $this->option('no-users')) {
            $this->call('db:seed', ['--class' => SampleUsersSeeder::class, '--force' => true]);
        }
        if ($this->option('production-users')) {
            $this->call('db:seed', ['--class' => \Database\Seeders\ProductionUsersSeeder::class, '--force' => true]);
        }
        $this->components->info('Done. Sign in with one of the sample accounts listed in README.md.');

        return self::SUCCESS;
    }
}
