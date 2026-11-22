<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSurveillanceData;
use Tests\TestCase;

class DashboardScopeTest extends TestCase
{
    use BuildsSurveillanceData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld(70);
    }

    public function test_every_analysis_page_renders_for_the_national_manager(): void
    {
        $m = $this->userFor('national_manager', $this->country);
        foreach (['/dashboard', '/trends', '/map', '/quality', '/vaccination', '/reporting', '/attention', '/alerts', '/exports', '/live/summary', '/data/districts.geojson'] as $url) {
            $this->actingAs($m)->get($url)->assertOk();
        }
        $this->actingAs($m)->get('/trends?disease=measles&area='.$this->districtA->id.'&from=2026-01-05&to=2026-03-30')->assertOk()->assertSee('Weekly values with denominators');
    }

    public function test_district_officer_sees_only_their_district_on_every_page(): void
    {
        $o = $this->userFor('district_officer', $this->districtA);
        $this->actingAs($o)->get('/dashboard')->assertOk()->assertSee('Alpha District')->assertDontSee('Bravo District');
        $this->actingAs($o)->get('/map')->assertOk()->assertDontSee('Bravo District');
        $square = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 0]]]];
        $this->districtA->update(['geometry' => $square]);
        $this->districtB->update(['geometry' => $square]);
        $res = $this->actingAs($o)->getJson('/data/districts.geojson')->assertOk();
        $features = collect($res->json('features'));
        $this->assertCount(2, $features, 'every district is drawn so the map reads as the country');
        $this->assertSame(['Alpha District'], $features->where('properties.accessible', true)->pluck('properties.name')->all());
        $outside = $features->where('properties.accessible', false)->first();
        $this->assertSame(['accessible' => false], $outside['properties'], 'outside districts carry no name, id or code');
        $this->assertStringNotContainsString('Bravo', $res->getContent());
        foreach (['/trends', '/map', '/quality', '/vaccination', '/reporting', '/attention', '/exports/download?dataset=weekly_cases', '/forecasts'] as $url) {
            $sep = str_contains($url, '?') ? '&' : '?';
            $this->actingAs($o)->get($url.$sep.'area='.$this->districtB->id)->assertForbidden();
            $this->actingAs($o)->get($url.$sep.'area='.$this->facB1->id)->assertForbidden();
        }
        $this->actingAs($o)->get('/fhir/MeasureReport?area='.$this->districtB->code)->assertForbidden();
        $this->actingAs($o)->get('/fhir/MeasureReport?area='.$this->districtA->code)->assertOk();
    }

    public function test_clinician_is_limited_to_their_facility(): void
    {
        $c = $this->userFor('clinician', $this->facA1);
        $this->actingAs($c)->get('/trends')->assertOk()->assertSee('Facility facA1');
        $this->actingAs($c)->get('/trends?area='.$this->facA2->id)->assertForbidden();
        $this->actingAs($c)->get('/trends?area='.$this->districtA->id)->assertForbidden();
    }

    public function test_lab_officer_cannot_open_dashboards_or_exports(): void
    {
        $l = $this->userFor('lab_officer', $this->districtA);
        foreach (['/trends', '/map', '/exports', '/alerts', '/forecasts', '/admin/users'] as $url) {
            $this->actingAs($l)->get($url)->assertForbidden();
        }
        $this->actingAs($l)->get('/lab')->assertOk();
    }

    public function test_admin_manages_users_but_cannot_read_surveillance_data(): void
    {
        $a = $this->userFor('system_admin', $this->country);
        $this->actingAs($a)->get('/admin/users')->assertOk();
        foreach (['/trends', '/cases', '/exports'] as $url) {
            $this->actingAs($a)->get($url)->assertForbidden();
        }
    }

    public function test_charts_state_their_axis_and_offer_a_data_table(): void
    {
        $m = $this->userFor('national_manager', $this->country);
        $body = $this->actingAs($m)->get('/trends?disease=malaria')->assertOk()->getContent();
        $this->assertStringContainsString('The axis starts at zero', $body);
        $this->assertStringContainsString('View as a data table', $body);
        $this->assertStringContainsString('<figcaption', $body);
        $this->assertStringContainsString('role="img"', $this->actingAs($m)->get('/map')->getContent() . 'role="img"');
    }

    public function test_aggregate_export_is_scoped_flagged_and_audited_and_has_no_case_data(): void
    {
        $o = $this->userFor('district_officer', $this->districtA);
        $res = $this->actingAs($o)->get('/exports/download?dataset=weekly_cases&disease=measles&breakdown=children');
        $res->assertOk();
        $csv = $res->streamedContent();
        $this->assertStringContainsString('aggregated export (no case-level data)', $csv);
        $this->assertStringContainsString('Facility facA1', $csv);
        $this->assertStringNotContainsString('Facility facB1', $csv);
        $this->assertStringContainsString('small_number', $csv);
        foreach (['pseudonym', 'birth_year', 'person'] as $field) {
            $this->assertStringNotContainsString($field, strtolower($csv));
        }
        $this->assertSame(1, AuditLog::where(['category' => 'export', 'action' => 'aggregate_csv', 'user_id' => $o->id])->count());
    }

    public function test_there_is_no_route_that_exports_case_records(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $name = (string) $route->getName();
            $uri = $route->uri();
            $this->assertFalse(str_contains($name, 'export') && str_contains($name, 'case'), "route $name exports cases");
            if (str_starts_with($uri, 'cases') || str_starts_with($uri, 'lab')) {
                $this->assertStringNotContainsString('csv', $uri);
                $this->assertStringNotContainsString('download', $uri);
                $this->assertStringNotContainsString('export', $uri);
            }
        }
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('cases.export'));
    }

    public function test_fhir_measure_report_has_the_expected_shape_and_content_type(): void
    {
        $m = $this->userFor('national_manager', $this->country);
        $res = $this->actingAs($m)->get('/fhir/MeasureReport?disease=measles&area='.$this->districtA->code)->assertOk();
        $this->assertStringContainsString('application/fhir+json', $res->headers->get('Content-Type'));
        $json = $res->json();
        $this->assertSame('MeasureReport', $json['resourceType']);
        $this->assertSame('summary', $json['type']);
        $this->assertSame('complete', $json['status']);
        $group = $json['group'][0];
        $this->assertSame('B05', $group['code']['coding'][0]['code']);
        $this->assertSame(4, $group['population'][0]['count'], 'two facilities x 2 cases in the week');
        $this->assertSame(100000, $group['population'][1]['count']);
        $this->assertEquals(4.0, $group['measureScore']['value']);
        $this->assertSame(1, AuditLog::where('action', 'fhir_measure_report')->count());
    }
}
