<?php

namespace Tests\Concerns;

use App\Models\Disease;
use App\Models\FacilityProfile;
use App\Models\OrgUnit;
use App\Models\User;
use App\Services\IndicatorEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\AlertRulesSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

/** Small, deterministic surveillance fixture: one country, one region, two districts with two reporting facilities each. */
trait BuildsSurveillanceData
{
    protected OrgUnit $country;
    protected OrgUnit $region;
    protected OrgUnit $districtA;
    protected OrgUnit $districtB;
    protected OrgUnit $facA1;
    protected OrgUnit $facA2;
    protected OrgUnit $facB1;
    protected OrgUnit $facB2;
    protected CarbonImmutable $lastWeek;

    protected function buildWorld(int $weeks = 40, bool $withSeries = true): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(AlertRulesSeeder::class);

        $this->country = OrgUnit::factory()->country()->create();
        $this->region = OrgUnit::factory()->childOf($this->country, 'region')->create(['name' => 'Central']);
        $this->districtA = OrgUnit::factory()->childOf($this->region, 'district')->create(['name' => 'Alpha District']);
        $this->districtB = OrgUnit::factory()->childOf($this->region, 'district')->create(['name' => 'Bravo District']);
        foreach (['facA1' => $this->districtA, 'facA2' => $this->districtA, 'facB1' => $this->districtB, 'facB2' => $this->districtB] as $prop => $d) {
            $this->$prop = OrgUnit::factory()->childOf($d, 'facility')->create(['name' => 'Facility '.$prop]);
            FacilityProfile::create(['org_unit_id' => $this->$prop->id, 'facility_type' => 'hc3', 'sentinel' => true]);
        }
        foreach ([$this->districtA, $this->districtB] as $d) {
            foreach ([now()->year - 1, now()->year, now()->year + 1] as $y) {
                DB::table('populations')->insert(['org_unit_id' => $d->id, 'year' => $y, 'total' => 100000, 'under1' => 4000, 'under5' => 20000, 'women_15_49' => 22000, 'projected' => false]);
            }
        }
        $this->lastWeek = CarbonImmutable::today()->startOfWeek()->subWeek();
        if ($withSeries) {
            $this->loadSeries($weeks);
        }
    }

    /** Flat baseline of 2 cases per facility per week for every disease, all facilities reporting. */
    protected function loadSeries(int $weeks, array $override = []): void
    {
        $rows = [];
        $status = [];
        $diseases = Disease::pluck('id', 'code');
        foreach ([$this->facA1, $this->facA2, $this->facB1, $this->facB2] as $f) {
            for ($i = $weeks - 1; $i >= 0; $i--) {
                $week = $this->lastWeek->subWeeks($i)->toDateString();
                foreach ($diseases as $code => $id) {
                    $cases = $override[$f->id][$code][$week] ?? ($code === 'ebola' ? 0 : 2);
                    $rows[] = ['org_unit_id' => $f->id, 'disease_id' => $id, 'week_start' => $week, 'cases' => $cases, 'deaths' => 0, 'origin' => 'synthetic'];
                }
                $status[] = ['org_unit_id' => $f->id, 'week_start' => $week, 'received' => true, 'on_time' => true];
            }
        }
        foreach (array_chunk($rows, 500) as $c) {
            DB::table('facility_reports')->insert($c);
        }
        DB::table('reporting_statuses')->insert($status);
        (new IndicatorEngine)->rebuildAll();
    }

    protected function userFor(string $role, OrgUnit ...$units): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);
        $u->orgUnits()->attach(collect($units)->pluck('id'));

        return $u;
    }

    /** Raises one disease in one facility for the last N weeks. */
    protected function outbreak(OrgUnit $facility, string $disease, int $cases, int $lastWeeks = 2): array
    {
        $o = [];
        for ($i = 0; $i < $lastWeeks; $i++) {
            $o[$facility->id][$disease][$this->lastWeek->subWeeks($i)->toDateString()] = $cases;
        }

        return $o;
    }
}
