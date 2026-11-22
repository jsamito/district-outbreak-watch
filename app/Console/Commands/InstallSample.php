<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallSample extends Command
{
    protected $signature = 'surveillance:install-sample {--force : Skip the confirmation questions (for scripts only)}';

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
        $this->call('db:seed', ['--class' => \Database\Seeders\UsersSeeder::class, '--force' => true]);
        $this->components->info('Done. The accounts listed in README.md are ready to use (password Admin@123).');

        return self::SUCCESS;
    }
}
