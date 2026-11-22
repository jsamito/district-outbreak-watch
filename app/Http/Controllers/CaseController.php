<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\CaseEvent;
use App\Models\CaseRecord;
use App\Models\Disease;
use App\Models\LabResult;
use App\Models\OrgUnit;
use App\Models\Person;
use App\Services\CaseIngestService;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Case records (EHR-lite). Individual cases can be viewed and updated here by authorised roles only.
 * There is deliberately no export, download or print-all route for case-level data.
 */
class CaseController extends Controller
{
    use ResolvesFilters;

    public function __construct(private CaseIngestService $ingest) {}

    private function full(Request $request): bool
    {
        return $request->user()->can('view cases');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $query = CaseRecord::visibleTo($user)->with('disease', 'facility', 'person')->where('classification', '!=', 'discarded');
        foreach (['classification', 'status'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, $request->input($f));
            }
        }
        if ($request->filled('disease')) {
            $query->whereHas('disease', fn ($q) => $q->where('code', $request->input('disease')));
        }
        if ($request->filled('facility')) {
            $query->where('reporting_org_unit_id', $request->integer('facility'));
        } elseif ($request->filled('area')) {
            $area = OrgUnit::find($request->integer('area'));
            if ($area && $user->canAccessOrgUnit($area)) {
                $query->whereIn('reporting_org_unit_id', OrgUnit::within($area)->select('id'));
            }
        }
        if ($request->filled('from')) {
            $query->where('notification_date', '>=', CarbonImmutable::parse($request->input('from'))->toDateString());
        }
        if ($request->filled('to')) {
            $query->where('notification_date', '<=', CarbonImmutable::parse($request->input('to'))->toDateString());
        }
        $cases = $query->orderByDesc('notification_date')->orderByDesc('id')->paginate(40)->withQueryString();

        Audit::log(Audit::DATA_ACCESS, 'case_list_viewed', null, ['filters' => $request->only('disease', 'classification', 'status', 'area', 'facility', 'from', 'to'), 'rows' => $cases->count()]);

        return view('cases.index', [
            'title' => 'Cases', 'cases' => $cases, 'full' => $this->full($request), 'diseases' => Disease::where('case_based', true)->orderBy('name')->get(),
            'areaOptions' => $this->areaOptions($user), 'f' => $request->all(),
        ]);
    }

    public function show(Request $request, CaseRecord $case): View
    {
        $full = $request->user()->can('view', $case);
        abort_unless($full || $request->user()->can('viewPseudonymised', $case), 403);
        $case->load('person', 'disease', 'facility.parent', 'labResults', 'events.user', 'encounters');
        Audit::log(Audit::DATA_ACCESS, 'case_viewed', $case, ['detail' => $full ? 'full' : 'pseudonymised']);

        return view('cases.show', [
            'title' => 'Case '.Str::substr($case->person->pseudonym, 0, 8), 'subtitle' => $case->disease->name, 'case' => $case, 'full' => $full,
            'canEdit' => $request->user()->can('update', $case), 'canLab' => $request->user()->can('enterLabResult', $case),
            'vaccinations' => $full ? $case->person->vaccinations()->with('antigen')->get() : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $facilities = $user->scopedOrgUnits()->where('type', OrgUnit::FACILITY)
            ->whereIn('id', fn ($q) => $q->select('org_unit_id')->from('facility_profiles')->where('sentinel', true))->orderBy('name')->get(['id', 'name', 'code']);

        return view('cases.create', ['title' => 'Notify a case', 'diseases' => Disease::where('case_based', true)->orderBy('priority')->orderBy('name')->get(), 'facilities' => $facilities, 'today' => now()->toDateString()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'disease' => ['required', 'exists:diseases,code'], 'facility_code' => ['required', 'exists:org_units,code'], 'sex' => ['required', 'in:M,F,U'],
            'age_years' => ['required', 'integer', 'min:0', 'max:120'], 'onset_date' => ['required', 'date', 'before_or_equal:today'],
            'notification_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:onset_date'], 'classification' => ['required', 'in:suspected,probable,confirmed'],
            'hospitalised' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $facility = OrgUnit::where('code', $data['facility_code'])->first();
        abort_unless($facility && $request->user()->canAccessOrgUnit($facility), 403);
        $data['hospitalised'] = $request->boolean('hospitalised');

        try {
            $res = $this->ingest->ingest($data, 'form', 'form-'.Str::uuid(), $facility, null, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
        Audit::log(Audit::DATA_ACCESS, 'case_created', $res['case'], ['disease' => $data['disease']]);

        return redirect()->route('cases.show', $res['case'])->with('status', 'Case notified. Dashboards and alerts are updated.');
    }

    public function update(Request $request, CaseRecord $case): RedirectResponse
    {
        $this->authorize('update', $case);
        $data = $request->validate([
            'classification' => ['required', 'in:'.implode(',', CaseRecord::CLASSIFICATIONS)], 'outcome' => ['required', 'in:'.implode(',', CaseRecord::OUTCOMES)],
            'status' => ['required', 'in:'.implode(',', CaseRecord::STATUSES)], 'investigation_date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $before = $case->only('classification', 'outcome', 'status');
        $this->ingest->reclassify($case, $data['classification'], $data['outcome']);
        $case->update(['status' => $data['status'], 'investigation_date' => ($data['investigation_date'] ?? null) ?: $case->investigation_date]);
        foreach (['classification' => 'classified', 'outcome' => 'outcome', 'status' => 'investigated'] as $field => $type) {
            if ($before[$field] !== $data[$field]) {
                CaseEvent::create(['case_id' => $case->id, 'type' => $type, 'data' => [$field => $data[$field], 'from' => $before[$field]], 'user_id' => $request->user()->id, 'occurred_at' => now()]);
            }
        }
        Audit::log(Audit::DATA_ACCESS, 'case_updated', $case, ['changes' => array_diff_assoc($data, $before)]);

        return back()->with('status', 'Case updated.');
    }

    public function note(Request $request, CaseRecord $case): RedirectResponse
    {
        $this->authorize('update', $case);
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);
        CaseEvent::create(['case_id' => $case->id, 'type' => 'note', 'data' => ['text' => $data['note']], 'user_id' => $request->user()->id, 'occurred_at' => now()]);
        Audit::log(Audit::DATA_ACCESS, 'case_note_added', $case);

        return back()->with('status', 'Note added.');
    }

    public function requestLab(Request $request, CaseRecord $case): RedirectResponse
    {
        $this->authorize('enterLabResult', $case);
        $data = $request->validate(['specimen_type' => ['required', 'string', 'max:30'], 'test_code' => ['required', 'string', 'max:30'], 'collected_at' => ['required', 'date', 'before_or_equal:today']]);
        LabResult::create($data + ['case_id' => $case->id, 'result' => 'pending', 'entered_by' => $request->user()->id]);
        CaseEvent::create(['case_id' => $case->id, 'type' => 'lab_result', 'data' => ['requested' => $data['test_code']], 'user_id' => $request->user()->id, 'occurred_at' => now()]);
        Audit::log(Audit::DATA_ACCESS, 'lab_requested', $case);

        return back()->with('status', 'Laboratory test recorded as pending.');
    }
}
