<?php

namespace Database\Seeders;

use App\Models\AlertPreference;
use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Accounts for the live installation at public-health-surveillance.joseneous.com.
 *
 * The three administrators have full access to the whole country. The Lira accounts are tied to Lira district and its
 * Regional Referral Hospital so the role limits can be shown with real places. Every initial password is temporary:
 * the person must choose their own at first sign-in. A password is only set when the account is created, so running the
 * seeder again never resets a password someone has already changed. Edit the emails below to the real ones before the first run.
 */
class ProductionUsersSeeder extends Seeder
{
    public const LIRA_DISTRICT = 'UG3083';
    public const LIRA_HOSPITAL = 'F11073118391'; // Lira Regional Referral Hospital

    /** email, name, role, job title, area code, initial password */
    public const USERS = [
        ['amito@joseneous.com', 'Amito', 'super_admin', 'System administrator', 'UG', 'puHoAJfapusn3P'],
        ['janet@joseneous.com', 'Janet', 'super_admin', 'Administrator', 'UG', 'hHUVJAq5exb2st'],
        ['susan@joseneous.com', 'Susan', 'super_admin', 'Administrator', 'UG', '5NV3xGWumiJA6N'],
        ['dho.lira@joseneous.com', 'Lira District Health Officer', 'district_officer', 'District Health Officer, Lira', self::LIRA_DISTRICT, 'BuNYfSfYMCwtdk'],
        ['epi.lira@joseneous.com', 'Lira Epidemiologist', 'epidemiologist', 'Epidemiologist, Lira', self::LIRA_DISTRICT, 't5UoLmwwEmctfy'],
        ['clinician.lira@joseneous.com', 'Lira Hospital Clinician', 'clinician', 'Clinician, Lira Regional Referral Hospital', self::LIRA_HOSPITAL, 'e8X47camodJamn'],
        ['lab.lira@joseneous.com', 'Lira Hospital Laboratory', 'lab_officer', 'Laboratory officer, Lira Regional Referral Hospital', self::LIRA_HOSPITAL, 'TJm3deCAKPyvCz'],
    ];

    public function run(): void
    {
        $codes = collect(self::USERS)->pluck(4)->unique();
        $areas = OrgUnit::whereIn('code', $codes)->get()->keyBy('code');
        $missing = $codes->diff($areas->keys());
        if ($missing->isNotEmpty()) {
            throw new \RuntimeException('Area codes not found (import the geography first): '.$missing->implode(', '));
        }

        foreach (self::USERS as [$email, $name, $role, $title, $areaCode, $password]) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $user = User::create(['email' => $email, 'name' => $name, 'password' => $password, 'job_title' => $title, 'active' => true, 'must_change_password' => true]);
            } else {
                $user->update(['name' => $name, 'job_title' => $title, 'active' => true]);
            }
            $user->syncRoles([$role]);
            $user->orgUnits()->sync([$areas[$areaCode]->id]);

            // Role accounts have no mailbox yet: keep alerts on screen only so no email is sent to an address that may not exist.
            if (! in_array($role, ['super_admin'], true)) {
                AlertPreference::updateOrCreate(['user_id' => $user->id], ['mode' => 'off']);
            }
        }
    }
}
