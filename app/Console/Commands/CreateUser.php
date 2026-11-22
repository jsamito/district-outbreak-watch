<?php

namespace App\Console\Commands;

use App\Models\OrgUnit;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateUser extends Command
{
    protected $signature = 'surveillance:create-user
        {email : Sign-in email address}
        {name : Full name}
        {role : system_admin, national_manager, epidemiologist, district_officer, clinician or lab_officer}
        {--area=* : Org unit code(s) the user is responsible for (e.g. UG, UG1001, F123); defaults to the country for national roles}
        {--title= : Job title}
        {--password= : Password (a random one is generated and shown once when omitted)}';

    protected $description = 'Create a user account with a role and area of responsibility';

    public function handle(): int
    {
        $role = $this->argument('role');
        if (! array_key_exists($role, RolesAndPermissionsSeeder::ROLES)) {
            $this->error('Unknown role. Choose one of: '.implode(', ', array_keys(RolesAndPermissionsSeeder::ROLES)));

            return self::FAILURE;
        }
        if (User::where('email', $this->argument('email'))->exists()) {
            $this->error('A user with that email already exists.');

            return self::FAILURE;
        }

        $codes = $this->option('area');
        if (! $codes && in_array($role, ['super_admin', 'system_admin', 'national_manager', 'epidemiologist'], true)) {
            $codes = [config('surveillance.country.code')];
        }
        $units = OrgUnit::whereIn('code', $codes)->get();
        if ($units->count() !== count($codes) || $units->isEmpty()) {
            $this->error('Every --area must be an existing org unit code, and at least one is required for this role.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(14, symbols: false);
        $user = User::create([
            'name' => $this->argument('name'), 'email' => $this->argument('email'), 'password' => $password, 'must_change_password' => true,
            'job_title' => $this->option('title') ?: str_replace('_', ' ', $role), 'active' => true,
        ]);
        $user->assignRole($role);
        $user->orgUnits()->attach($units->pluck('id'));
        Audit::log(Audit::CONFIG, 'user_created', $user, ['role' => $role, 'areas' => $codes]);

        $this->info("Created {$user->email} as $role for ".$units->pluck('name')->join(', ').'.');
        if (! $this->option('password')) {
            $this->line("Generated password (shown once): $password");
        }

        return self::SUCCESS;
    }
}
