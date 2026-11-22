<?php

namespace App\Http\Controllers;

use App\Models\CaseEvent;
use App\Models\CaseRecord;
use App\Models\LabResult;
use App\Services\CaseIngestService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Laboratory worklist: sees only what is needed to enter a result (no demographics beyond age group). */
class LabController extends Controller
{
    public function index(Request $request): View
    {
        $pending = LabResult::where('result', 'pending')->whereHas('case', fn ($q) => $q->visibleTo($request->user()))
            ->with('case.disease', 'case.person', 'case.facility.parent')->orderBy('collected_at')->limit(200)->get();
        Audit::log(Audit::DATA_ACCESS, 'lab_worklist_viewed', null, ['rows' => $pending->count()]);

        return view('lab.index', ['title' => 'Lab worklist', 'pending' => $pending]);
    }

    public function result(Request $request, LabResult $lab, CaseIngestService $ingest): RedirectResponse
    {
        $case = $lab->case;
        $this->authorize('enterLabResult', $case);
        $data = $request->validate(['result' => ['required', 'in:positive,negative,indeterminate'], 'resulted_at' => ['required', 'date', 'before_or_equal:today']]);
        $lab->update(['result' => $data['result'], 'resulted_at' => $data['resulted_at'], 'entered_by' => $request->user()->id, 'lab_org_unit_id' => $request->user()->orgUnits()->first()?->id]);
        CaseEvent::create(['case_id' => $case->id, 'type' => 'lab_result', 'data' => ['test' => $lab->test_code, 'result' => $data['result']], 'user_id' => $request->user()->id, 'occurred_at' => now()]);
        if ($data['result'] === 'positive' && in_array($case->classification, ['suspected', 'probable'], true)) {
            $ingest->reclassify($case, 'confirmed', $case->outcome);
            CaseEvent::create(['case_id' => $case->id, 'type' => 'classified', 'data' => ['classification' => 'confirmed', 'reason' => 'Positive laboratory result'], 'user_id' => $request->user()->id, 'occurred_at' => now()]);
        }
        Audit::log(Audit::DATA_ACCESS, 'lab_result_entered', $case, ['result' => $data['result']]);

        return back()->with('status', 'Result saved.');
    }
}
