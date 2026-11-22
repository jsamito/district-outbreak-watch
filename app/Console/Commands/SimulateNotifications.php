<?php

namespace App\Console\Commands;

use App\Models\Disease;
use App\Models\OrgUnit;
use App\Services\CaseIngestService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SimulateNotifications extends Command
{
    protected $signature = 'surveillance:simulate-notifications {--count=10} {--interval=2 : Seconds between notifications} {--disease=measles} {--district=Kasese}';

    protected $description = 'Send test notifications through the live ingestion pipeline (for testing dashboards, alerts and live updates)';

    public function handle(CaseIngestService $ingest): int
    {
        $disease = Disease::where('code', $this->option('disease'))->where('case_based', true)->first();
        $district = OrgUnit::where('type', 'district')->where('name', $this->option('district'))->first();
        if (! $disease || ! $district) {
            $this->error('Unknown disease or district.');

            return self::FAILURE;
        }
        $facilities = OrgUnit::where('parent_id', $district->id)->whereIn('id', fn ($q) => $q->select('org_unit_id')->from('facility_profiles')->where('sentinel', true))->pluck('code');
        if ($facilities->isEmpty()) {
            $this->error('No reporting facilities in that district.');

            return self::FAILURE;
        }
        for ($i = 1; $i <= (int) $this->option('count'); $i++) {
            $today = CarbonImmutable::today();
            $res = $ingest->ingest([
                'disease' => $disease->code, 'facility_code' => $facilities->random(), 'sex' => random_int(0, 1) ? 'F' : 'M', 'age_years' => random_int(0, 40),
                'onset_date' => $today->subDays(random_int(0, 4))->toDateString(), 'notification_date' => $today->toDateString(), 'classification' => 'suspected', 'hospitalised' => random_int(0, 3) === 0,
            ], 'simulator', 'sim-'.Str::uuid());
            $this->line("[$i] {$disease->name} notified in {$district->name} (week {$res['case']->epi_week->toDateString()})");
            if ($i < (int) $this->option('count')) {
                sleep((int) $this->option('interval'));
            }
        }

        return self::SUCCESS;
    }
}
