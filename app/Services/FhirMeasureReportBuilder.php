<?php

namespace App\Services;

use App\Models\Disease;
use App\Models\OrgUnit;
use Carbon\CarbonImmutable;

/**
 * Builds FHIR R4 summary MeasureReports for weekly case counts, informed by the HL7 PublicHealthMeasureReport
 * profile: one report per area and period, one group per disease, counts of cases and population with the
 * incidence per 100,000 as the measure score. Aggregated data only.
 */
class FhirMeasureReportBuilder
{
    public function __construct(private AggregateQuery $q) {}

    public function build(OrgUnit $area, CarbonImmutable $from, CarbonImmutable $to, ?Disease $only = null): array
    {
        $groups = [];
        $population = $this->q->population($area, (int) $to->year);
        foreach (($only ? collect([$only]) : Disease::orderBy('name')->get()) as $d) {
            $cases = (int) $this->q->total($area, $d->id, $from, $to);
            $group = [
                'code' => ['coding' => [['system' => 'http://hl7.org/fhir/sid/icd-10', 'code' => $d->icd10_code, 'display' => $d->name]]],
                'population' => [['code' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/measure-population', 'code' => 'numerator']]], 'count' => $cases]],
            ];
            if ($population) {
                $group['population'][] = ['code' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/measure-population', 'code' => 'denominator']]], 'count' => $population];
                $group['measureScore'] = ['value' => round($cases / $population * 100000, 2), 'unit' => 'per 100,000 population', 'system' => 'http://unitsofmeasure.org', 'code' => '/100000'];
            } else {
                $group['measureScore'] = ['value' => $cases, 'unit' => 'cases'];
            }
            $groups[] = $group;
        }

        return [
            'resourceType' => 'MeasureReport',
            'id' => 'weekly-cases-'.$area->code.'-'.$from->toDateString(),
            'status' => 'complete',
            'type' => 'summary',
            'measure' => url('/fhir/Measure/weekly-cases'),
            'date' => now()->toIso8601String(),
            'subject' => ['identifier' => ['system' => url('/fhir/org-unit'), 'value' => $area->code], 'display' => $area->name],
            'period' => ['start' => $from->toDateString(), 'end' => $to->endOfWeek()->toDateString()],
            'group' => $groups,
        ];
    }
}
