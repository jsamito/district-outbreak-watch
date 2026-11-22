<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\Antigen;
use App\Models\Disease;
use App\Models\OrgUnit;
use App\Services\AggregateQuery;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Aggregated CSV export. There is deliberately no case-level (line list) export anywhere in the application. */
class ExportController extends Controller
{
    use ResolvesFilters;

    private const DATASETS = ['weekly_cases' => 'Weekly cases', 'reporting' => 'Reporting completeness', 'vaccination' => 'Vaccination coverage'];

    public function __construct(private AggregateQuery $q) {}

    public function index(Request $request): View
    {
        $area = $this->area($request);
        $disease = $this->disease($request, 'measles');
        [$from, $to] = $this->range($request, 52);
        $dataset = array_key_exists($request->input('dataset'), self::DATASETS) ? $request->input('dataset') : 'weekly_cases';
        $extra = '<div class="col-sm-6 col-md-3 form-group"><label for="f-dataset">Dataset</label><select id="f-dataset" name="dataset" class="form-control">';
        foreach (self::DATASETS as $k => $label) {
            $extra .= '<option value="'.$k.'"'.($k === $dataset ? ' selected' : '').'>'.e($label).'</option>';
        }
        $extra .= '</select></div><div class="col-sm-6 col-md-2 form-group"><label for="f-breakdown">Rows</label><select id="f-breakdown" name="breakdown" class="form-control"><option value="area">This area only</option><option value="children"'.($request->input('breakdown') === 'children' ? ' selected' : '').'>Each area below</option></select></div>';

        return view('exports', $this->filterData($request, $area, $disease, $from, $to) + ['title' => 'Export data', 'extra' => $extra, 'dataset' => $dataset]);
    }

    public function download(Request $request): StreamedResponse
    {
        $request->validate(['dataset' => ['required', 'in:'.implode(',', array_keys(self::DATASETS))], 'breakdown' => ['nullable', 'in:area,children']]);
        $area = $this->area($request);
        $disease = $this->disease($request, 'measles');
        [$from, $to] = $this->range($request, 52);
        $dataset = $request->input('dataset');
        $children = $request->input('breakdown') === 'children';

        $units = collect([$area]);
        if ($children && $area->type !== OrgUnit::FACILITY) {
            $units = $area->children()->where('active', true)->orderBy('name')->get();
            if ($area->type === OrgUnit::DISTRICT) {
                $units = $units->whereIn('id', DB::table('facility_profiles')->where('sentinel', true)->pluck('org_unit_id'));
            }
        }
        $units = $units->filter(fn (OrgUnit $u) => $request->user()->canAccessOrgUnit($u))->values();

        Audit::log(Audit::EXPORT, 'aggregate_csv', $area, ['dataset' => $dataset, 'disease' => $disease->code, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'rows' => $children ? 'children' : 'area']);

        $floor = (int) config('surveillance.small_number_floor');
        $name = 'outbreak-watch-'.$dataset.'-'.$area->code.'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($units, $area, $dataset, $disease, $from, $to, $floor) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['# '.config('app.name').' - aggregated export (no case-level data)']);
            fputcsv($out, ['# Exported '.now()->toDateTimeString().' by an authorised user; area: '.$area->name.'; period: '.$from->toDateString().' to '.$to->toDateString()]);
            fputcsv($out, ['# Counts below '.$floor.' are flagged as small numbers and should be interpreted with caution. Sample data are synthetic: see the provenance page.']);

            if ($dataset === 'weekly_cases') {
                fputcsv($out, ['area_code', 'area_name', 'disease', 'icd10', 'week_start', 'cases', 'population', 'incidence_per_100000', 'small_number']);
                foreach ($units as $u) {
                    foreach ($this->q->series($u, $disease->id, $from, $to) as $week => $cases) {
                        $pop = $this->q->population($u, (int) substr($week, 0, 4));
                        fputcsv($out, [$u->code, $u->name, $disease->name, $disease->icd10_code, $week, (int) $cases, $pop, $pop ? round($cases / $pop * 100000, 2) : '', ($cases > 0 && $cases < $floor) ? 1 : 0]);
                    }
                }
            } elseif ($dataset === 'reporting') {
                fputcsv($out, ['area_code', 'area_name', 'week_start', 'reports_received', 'reports_expected', 'completeness_percent']);
                foreach ($units as $u) {
                    $rows = DB::table('indicator_values')->where(['org_unit_id' => $u->id, 'indicator_id' => \App\Models\Indicator::idFor(\App\Models\Indicator::REPORTING), 'period_type' => 'week'])
                        ->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])->orderBy('period_start')->get();
                    foreach ($rows as $r) {
                        fputcsv($out, [$u->code, $u->name, $r->period_start, (int) $r->numerator, (int) $r->denominator, round((float) $r->value, 1)]);
                    }
                }
            } else {
                fputcsv($out, ['area_code', 'area_name', 'vaccine', 'month', 'doses', 'target_population', 'coverage_percent', 'above_100_percent']);
                foreach ($units as $u) {
                    foreach (Antigen::orderBy('id')->get() as $a) {
                        foreach ($this->q->vaccinationSeries($u, $a->id, $from->startOfMonth(), $to) as $month => $v) {
                            fputcsv($out, [$u->code, $u->name, $a->code, $month, (int) $v['doses'], (int) $v['target'], $v['value'] === null ? '' : round($v['value'], 1), ($v['value'] ?? 0) > 100 ? 1 : 0]);
                        }
                    }
                }
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
