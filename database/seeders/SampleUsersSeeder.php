<?php

namespace Database\Seeders;

use App\Models\FacilityProfile;
use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Accounts for local development and acceptance testing, one per role.
 * The shared password comes from SAMPLE_USER_PASSWORD in .env (see .env.example).
 * Never run this seeder against a production database.
 */
class SampleUsersSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SAMPLE_USER_PASSWORD', 'ChangeMe-Local-2026');
        $country = OrgUnit::where('code', config('surveillance.country.code'))->firstOrFail();
        $kasese = OrgUnit::where('type', 'district')->where('name', 'Kasese')->first();
        $kampala = OrgUnit::where('type', 'district')->where('name', 'Kampala')->first();
        $facility = $kasese ? FacilityProfile::where('sentinel', true)->whereHas('orgUnit', fn ($q) => $q->where('parent_id', $kasese->id))->first()?->orgUnit : null;

        $users = [
            ['admin@outbreak-watch.test', 'Grace Admin', 'system_admin', 'System administrator', [$country]],
            ['manager@outbreak-watch.test', 'Peter Manager', 'national_manager', 'National surveillance manager', [$country]],
            ['epi@outbreak-watch.test', 'Esther Epidemiologist', 'epidemiologist', 'Epidemiologist', [$country]],
            ['officer.kasese@outbreak-watch.test', 'David Officer', 'district_officer', 'District Health Officer, Kasese', array_filter([$kasese])],
            ['officer.kampala@outbreak-watch.test', 'Sarah Officer', 'district_officer', 'District Health Officer, Kampala', array_filter([$kampala])],
            ['clinician@outbreak-watch.test', 'John Clinician', 'clinician', 'Clinician, sentinel facility', array_filter([$facility])],
            ['lab@outbreak-watch.test', 'Ruth Lab', 'lab_officer', 'Laboratory officer, Kasese', array_filter([$kasese])],
        ];

        foreach ($users as [$email, $name, $role, $title, $areas]) {
            if (! $areas) {
                continue;
            }
            $user = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'job_title' => $title, 'active' => true]);
            $user->syncRoles([$role]);
            $user->orgUnits()->sync(collect($areas)->pluck('id'));
        }
    }
}
