<?php

namespace Tests\Feature;

use App\Models\OrgUnit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopeAccessTest extends TestCase
{
    use RefreshDatabase;

    private OrgUnit $country;
    private OrgUnit $districtA;
    private OrgUnit $districtB;
    private OrgUnit $facilityA;
    private OrgUnit $facilityB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->country = OrgUnit::factory()->country()->create();
        $region = OrgUnit::factory()->childOf($this->country, OrgUnit::REGION)->create();
        $this->districtA = OrgUnit::factory()->childOf($region, OrgUnit::DISTRICT)->create(['name' => 'District A']);
        $this->districtB = OrgUnit::factory()->childOf($region, OrgUnit::DISTRICT)->create(['name' => 'District B']);
        $this->facilityA = OrgUnit::factory()->childOf($this->districtA, OrgUnit::FACILITY)->create();
        $this->facilityB = OrgUnit::factory()->childOf($this->districtB, OrgUnit::FACILITY)->create();
    }

    private function userFor(string $role, OrgUnit $unit): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->orgUnits()->attach($unit);

        return $user;
    }

    public function test_district_officer_sees_own_district_and_facilities_only(): void
    {
        $officer = $this->userFor('district_officer', $this->districtA);

        $this->actingAs($officer)->get(route('areas.show', $this->districtA))->assertOk();
        $this->actingAs($officer)->get(route('areas.show', $this->facilityA))->assertOk();
        $this->actingAs($officer)->get(route('areas.show', $this->districtB))->assertForbidden();
        $this->actingAs($officer)->get(route('areas.show', $this->facilityB))->assertForbidden();
        $this->actingAs($officer)->get(route('areas.show', $this->country))->assertForbidden();
    }

    public function test_changing_the_id_in_the_url_never_reveals_another_district(): void
    {
        $officer = $this->userFor('district_officer', $this->districtA);

        foreach ([$this->districtB, $this->facilityB, $this->country] as $other) {
            $this->actingAs($officer)->get('/areas/'.$other->id)->assertForbidden();
        }
    }

    public function test_national_manager_sees_everything(): void
    {
        $manager = $this->userFor('national_manager', $this->country);

        foreach ([$this->country, $this->districtA, $this->districtB, $this->facilityB] as $unit) {
            $this->actingAs($manager)->get(route('areas.show', $unit))->assertOk();
        }
    }

    public function test_user_without_assignment_sees_nothing(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinician');

        $this->actingAs($user)->get(route('areas.show', $this->districtA))->assertForbidden();
        $this->assertSame(0, $user->scopedOrgUnits()->count());
    }

    public function test_scoped_query_matches_the_assignment_subtree(): void
    {
        $officer = $this->userFor('district_officer', $this->districtA);

        $ids = $officer->scopedOrgUnits()->pluck('id')->all();
        sort($ids);

        $this->assertSame([$this->districtA->id, $this->facilityA->id], $ids);
    }

    public function test_path_prefix_does_not_leak_between_similar_codes(): void
    {
        $a = OrgUnit::factory()->childOf($this->districtA, OrgUnit::FACILITY)->create(['code' => 'F1', 'path' => $this->districtA->path.'/F1']);
        $b = OrgUnit::factory()->childOf($this->districtA, OrgUnit::FACILITY)->create(['code' => 'F10', 'path' => $this->districtA->path.'/F10']);
        $user = $this->userFor('clinician', $a);

        $this->assertTrue($user->canAccessOrgUnit($a));
        $this->assertFalse($user->canAccessOrgUnit($b));
    }

    public function test_inactive_user_is_denied_even_with_scope(): void
    {
        $officer = $this->userFor('district_officer', $this->districtA);
        $officer->update(['active' => false]);

        $this->actingAs($officer)->get(route('areas.show', $this->districtA))->assertForbidden();
    }

    public function test_dashboard_lists_navigation_by_permission(): void
    {
        $clinician = $this->userFor('clinician', $this->facilityA);

        $this->actingAs($clinician)->get(route('dashboard'))->assertOk()->assertSee('Your access');
    }
}
