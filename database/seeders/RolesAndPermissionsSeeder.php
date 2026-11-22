<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public const ROLES = [
        // Everything the other roles can do together, for the people who run the whole system.
        'super_admin' => [
            'manage users', 'manage data sources', 'manage alert rules', 'view audit log', 'manage api clients', 'review data quality',
            'view aggregates', 'view forecasts', 'view alerts', 'acknowledge alerts', 'export aggregates', 'annotate charts', 'view audit summary',
            'view cases', 'view cases pseudonymised', 'edit cases', 'enter lab results', 'view lab worklist',
        ],
        'system_admin' => [
            'manage users', 'manage data sources', 'manage alert rules', 'view audit log', 'manage api clients', 'review data quality',
        ],
        'national_manager' => [
            'view aggregates', 'view forecasts', 'view alerts', 'acknowledge alerts', 'manage alert rules',
            'export aggregates', 'annotate charts', 'view audit summary', 'review data quality',
        ],
        'epidemiologist' => [
            'view aggregates', 'view forecasts', 'view alerts', 'view cases pseudonymised',
            'export aggregates', 'annotate charts', 'review data quality',
        ],
        'district_officer' => [
            'view aggregates', 'view forecasts', 'view alerts', 'acknowledge alerts', 'view cases',
            'edit cases', 'enter lab results', 'export aggregates', 'annotate charts', 'review data quality',
        ],
        'clinician' => [
            'view aggregates', 'view alerts', 'acknowledge alerts', 'view cases', 'edit cases',
            'export aggregates',
        ],
        'lab_officer' => [
            'view lab worklist', 'enter lab results',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = collect(self::ROLES)->flatten()->unique();
        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }
        foreach (self::ROLES as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
