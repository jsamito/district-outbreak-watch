<?php

namespace App\Console\Commands;

use Database\Seeders\SampleUsersSeeder;
use Illuminate\Console\Command;

class InstallSample extends Command
{
    protected $signature = 'surveillance:install-sample {--no-users : Do not create the sample role accounts} {--production-users : Create the live administrator and Lira accounts (ProductionUsersSeeder)} {--force : Skip the confirmation questions (for scripts only)}';

    protected $description = 'Build a complete working system from scratch: database, Uganda geography, reference data, sample series, cases, projections and alerts';

    public function handle(): int
    {
        $db = config('database.connections.'.config('database.default'));
        $existing = collect(\Illuminate\Support\Facades\DB::select('SHOW TABLES'))->count();
        $this->newLine();
        $this->components->warn("This ERASES every table in database \"{$db['database']}\" on {$db['host']} ({$existing} tables now) and rebuilds it with sample data.");
        $this->line('  Users, cases, alerts and settings in that database will be lost. Take a backup first if it holds anything you need.');
        if (! $this->option('force') && ! $this->confirm('Erase the database and install the sample system?', false)) {
            $this->components->info('Cancelled. Nothing was changed.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && app()->isProduction()) {
            $typed = $this->ask("Production environment. Type the database name ({$db['database']}) to confirm");
            if ($typed !== $db['database']) {
                $this->components->error('The name did not match. Nothing was changed.');

                return self::FAILURE;
            }
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
