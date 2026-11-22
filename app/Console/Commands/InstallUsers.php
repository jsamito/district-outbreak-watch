<?php

namespace App\Console\Commands;

use Database\Seeders\UsersSeeder;
use Illuminate\Console\Command;

class InstallUsers extends Command
{
    protected $signature = 'surveillance:install-users {--reset-passwords : Put every account back on the initial password (Admin@123)}';

    protected $description = 'Create or update the standard accounts (Amito, Susan, Janet and the Lira role accounts) without touching any other data';

    public function handle(): int
    {
        $seeder = new UsersSeeder;
        $seeder->resetPasswords = (bool) $this->option('reset-passwords');
        $seeder->setContainer(app())->setCommand($this)->run();
        $this->info('Accounts are ready. Initial password: '.UsersSeeder::INITIAL_PASSWORD.($seeder->resetPasswords ? ' (reset for every account)' : ' (kept as chosen for accounts that already existed)'));

        return self::SUCCESS;
    }
}
