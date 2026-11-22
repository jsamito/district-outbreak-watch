<?php

namespace Database\Seeders;

use App\Models\AlertRule;
use App\Models\Disease;
use Illuminate\Database\Seeder;

class AlertRulesSeeder extends Seeder
{
    public function run(): void
    {
        AlertRule::updateOrCreate(['name' => 'Outbreak signal: all epidemic-prone diseases'], [
            'disease_id' => null, 'org_unit_id' => null, 'method' => 'outbreak_signal',
            'params' => ['baseline_weeks' => 8, 'guard_weeks' => 1, 'sigmas' => 3.0],
            'severity' => 'auto', 'min_count' => 5, 'cooldown_hours' => 336, 'escalate_after_minutes' => 720,
            'audience_roles' => ['district_officer', 'clinician'], 'enabled' => true,
        ]);
        $immediate = Disease::where('immediate_notifiable', true)->first();
        AlertRule::updateOrCreate(['name' => 'Single case of an immediately notifiable disease'], [
            'disease_id' => null, 'org_unit_id' => null, 'method' => 'single_case', 'params' => [],
            'severity' => 'high', 'min_count' => 1, 'cooldown_hours' => 168, 'escalate_after_minutes' => 120,
            'audience_roles' => ['district_officer', 'national_manager'], 'enabled' => (bool) $immediate,
        ]);
        // Off by default: it overlaps with the outbreak signal and would double the alerts for the same event. Turn it on per disease where it helps.
        AlertRule::updateOrCreate(['name' => 'Cluster: five cases in one district within 14 days'], [
            'disease_id' => null, 'org_unit_id' => null, 'method' => 'cluster', 'params' => ['cluster_days' => 14],
            'severity' => 'medium', 'min_count' => 5, 'cooldown_hours' => 336, 'escalate_after_minutes' => 720,
            'audience_roles' => ['district_officer'], 'enabled' => false,
        ]);
    }
}
