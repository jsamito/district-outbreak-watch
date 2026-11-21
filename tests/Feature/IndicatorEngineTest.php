<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\Indicator;
use App\Models\OrgUnit;
use App\Services\IndicatorEngine;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IndicatorEngineTest extends TestCase
{
    use RefreshDatabase;

    private OrgUnit $country;
    private OrgUnit $region;
    private OrgUnit $districtA;
    private OrgUnit $districtB;
    private OrgUnit $facA1;
    private OrgUnit $facA2;
    private OrgUnit $facB1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->country = OrgUnit::factory()->country()->create();
        $this->region = OrgUnit::factory()->childOf($this->country, 'region')->create();
        $this->districtA = OrgUnit::factory()->childOf($this->region, 'district')->create();
        $this->districtB = OrgUnit::factory()->childOf($this->region, 'district')->create();
        $this->facA1 = OrgUnit::factory()->childOf($this->districtA, 'facility')->create();
        $this->facA2 = OrgUnit::factory()->childOf($this->districtA, 'facility')->create();
        $this->facB1 = OrgUnit::factory()->childOf($this->districtB, 'facility')->create();
    }

    private function report(OrgUnit $unit, string $week, int $cases, string $disease = 'measles'): void
    {
        DB::table('facility_reports')->insert([
            'org_unit_id' => $unit->id, 'disease_id' => Disease::where('code', $disease)->value('id'),
            'week_start' => $week, 'cases' => $cases, 'deaths' => 0, 'origin' => 'synthetic',
        ]);
    }

    private function value(OrgUnit $unit, string $week, string $disease = 'measles'): ?float
    {
        $v = DB::table('indicator_values')->where([
            'org_unit_id' => $unit->id, 'indicator_id' => Indicator::idFor(Indicator::CASES),
            'dimension_id' => Disease::where('code', $disease)->value('id'), 'period_start' => $week,
        ])->value('value');

        return $v === null ? null : (float) $v;
    }

    public function test_case_counts_roll_up_through_every_area_level(): void
    {
        $this->report($this->facA1, '2026-11-02', 3);
        $this->report($this->facA2, '2026-11-02', 4);
        $this->report($this->facB1, '2026-11-02', 10);
        $this->report($this->facA1, '2026-11-02', 99, 'malaria');

        (new IndicatorEngine)->rebuildCases();

        $this->assertSame(3.0, $this->value($this->facA1, '2026-11-02'));
        $this->assertSame(7.0, $this->value($this->districtA, '2026-11-02'));
        $this->assertSame(10.0, $this->value($this->districtB, '2026-11-02'));
        $this->assertSame(17.0, $this->value($this->region, '2026-11-02'));
        $this->assertSame(17.0, $this->value($this->country, '2026-11-02'));
        $this->assertSame(99.0, $this->value($this->country, '2026-11-02', 'malaria'));
    }

    public function test_rebuilding_twice_gives_identical_results(): void
    {
        $this->report($this->facA1, '2026-11-02', 3);
        $engine = new IndicatorEngine;
        $engine->rebuildCases();
        $first = DB::table('indicator_values')->count();
        $engine->rebuildCases();

        $this->assertSame($first, DB::table('indicator_values')->count());
        $this->assertSame(3.0, $this->value($this->country, '2026-11-02'));
    }

    public function test_partial_rebuild_only_touches_the_requested_weeks(): void
    {
        $this->report($this->facA1, '2026-10-26', 2);
        $this->report($this->facA1, '2026-11-02', 5);
        $engine = new IndicatorEngine;
        $engine->rebuildCases();

        DB::table('facility_reports')->where('week_start', '2026-11-02')->update(['cases' => 8]);
        $engine->rebuildCases(['2026-11-02']);

        $this->assertSame(8.0, $this->value($this->country, '2026-11-02'));
        $this->assertSame(2.0, $this->value($this->country, '2026-10-26'));
    }

    public function test_reporting_completeness_is_a_proportion_of_expected_reports(): void
    {
        foreach ([[$this->facA1, true], [$this->facA2, false], [$this->facB1, true]] as [$unit, $received]) {
            DB::table('reporting_statuses')->insert(['org_unit_id' => $unit->id, 'week_start' => '2026-11-02', 'received' => $received, 'on_time' => $received]);
        }
        (new IndicatorEngine)->rebuildReporting();

        $id = Indicator::idFor(Indicator::REPORTING);
        $district = DB::table('indicator_values')->where(['org_unit_id' => $this->districtA->id, 'indicator_id' => $id])->first();
        $country = DB::table('indicator_values')->where(['org_unit_id' => $this->country->id, 'indicator_id' => $id])->first();

        $this->assertEquals(50.0, (float) $district->value);
        $this->assertEquals(66.6667, round((float) $country->value, 4));
        $this->assertEquals(3, (float) $country->denominator);
    }
}
