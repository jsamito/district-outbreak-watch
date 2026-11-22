<?php

namespace Database\Seeders;

use App\Models\AlertPreference;
use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Accounts for the live installation at https://public-health-surveillance.joseneous.com.
 *
 * Amito is the only super user. Susan is the District Health Officer for Lira and Janet is the regional officer for the
 * Northern region (where Lira is). The other Lira accounts let every role be shown with a real place. Every initial
 * password is temporary: the person must choose their own at first sign-in. A password is only set when the account
 * is created, so running the seeder again never resets a password someone has already changed.
 */
class ProductionUsersSeeder extends Seeder
{
    public const INITIAL_PASSWORD = 'Admin@123';
    public const NORTHERN_REGION = 'UG3';
    public const LIRA_DISTRICT = 'UG3083';
    public const LIRA_HOSPITAL = 'F11073118391'; // Lira Regional Referral Hospital

    /** email, name, role, job title, area code */
    public const USERS = [
        ['amito@gmail.com', 'Amito', 'super_admin', 'System administrator', 'UG'],
        ['susan@gmail.com', 'Susan', 'district_officer', 'District Health Officer, Lira', self::LIRA_DISTRICT],
        ['janet@gmail.com', 'Janet', 'regional_officer', 'Regional Officer, Northern region (Lira)', self::NORTHERN_REGION],
        ['epi.lira@gmail.com', 'Lira Epidemiologist', 'epidemiologist', 'Epidemiologist, Lira', self::LIRA_DISTRICT],
        ['clinician.lira@gmail.com', 'Lira Hospital Clinician', 'clinician', 'Clinician, Lira Regional Referral Hospital', self::LIRA_HOSPITAL],
        ['lab.lira@gmail.com', 'Lira Hospital Laboratory', 'lab_officer', 'Laboratory officer, Lira Regional Referral Hospital', self::LIRA_HOSPITAL],
    ];

    public function run(): void
    {
        $codes = collect(self::USERS)->pluck(4)->unique();
        $areas = OrgUnit::whereIn('code', $codes)->get()->keyBy('code');
        $missing = $codes->diff($areas->keys());
        if ($missing->isNotEmpty()) {
            throw new \RuntimeException('Area codes not found (import the geography first): '.$missing->implode(', '));
        }

        foreach (self::USERS as [$email, $name, $role, $title, $areaCode]) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $user = User::create(['email' => $email, 'name' => $name, 'password' => self::INITIAL_PASSWORD, 'job_title' => $title, 'active' => true, 'must_change_password' => true]);
            } else {
                $user->update(['name' => $name, 'job_title' => $title, 'active' => true]);
            }
            $user->syncRoles([$role]);
            $user->orgUnits()->sync([$areas[$areaCode]->id]);

            // The three named people receive alert emails. The extra role accounts are not real mailboxes: alerts stay on screen only.
            if (! in_array($email, ['amito@gmail.com', 'susan@gmail.com', 'janet@gmail.com'], true)) {
                AlertPreference::updateOrCreate(['user_id' => $user->id], ['mode' => 'off']);
            }
        }
    }
}
