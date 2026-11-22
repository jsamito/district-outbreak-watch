<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\Disease;
use App\Models\OrgUnit;
use App\Services\FhirMeasureReportBuilder;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FhirController extends Controller
{
    use ResolvesFilters;

    /** GET /fhir/MeasureReport?area=UG1001&disease=measles&from=2026-11-02&to=2026-11-09 */
    public function measureReport(Request $request, FhirMeasureReportBuilder $builder): JsonResponse
    {
        $request->validate(['area' => ['nullable', 'string', 'exists:org_units,code'], 'disease' => ['nullable', 'exists:diseases,code'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        if ($request->filled('area')) {
            $request->merge(['area' => OrgUnit::where('code', $request->input('area'))->value('id')]);
        }
        $area = $this->area($request);
        [$from, $to] = $this->range($request, 1);
        if (! $request->filled('from')) {
            $from = $to;
        }
        $disease = $request->filled('disease') ? Disease::where('code', $request->input('disease'))->first() : null;
        Audit::log(Audit::EXPORT, 'fhir_measure_report', $area, ['from' => $from->toDateString(), 'to' => $to->toDateString()]);

        return response()->json($builder->build($area, CarbonImmutable::parse($from), CarbonImmutable::parse($to), $disease), 200, ['Content-Type' => 'application/fhir+json', 'Cache-Control' => 'no-store']);
    }
}
