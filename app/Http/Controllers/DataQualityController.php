<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\DataQualityFlag;
use App\Models\OrgUnit;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DataQualityController extends Controller
{
    use ResolvesFilters;

    public function index(Request $request): View
    {
        $user = $request->user();
        $status = $request->input('status', 'open') === 'reviewed' ? 'reviewed' : 'open';
        $type = array_key_exists($request->input('type'), DataQualityFlag::TYPES) ? $request->input('type') : null;
        $area = null;
        if ($request->filled('area')) {
            $area = OrgUnit::find($request->integer('area'));
            abort_unless($area && $user->canAccessOrgUnit($area), 403);
        }

        $base = DataQualityFlag::visibleTo($user)->when($area, fn ($q) => $q->whereIn('org_unit_id', OrgUnit::within($area)->select('id')));
        $counts = (clone $base)->open()->selectRaw('type, COUNT(*) n')->groupBy('type')->pluck('n', 'type');
        $flags = (clone $base)->with('orgUnit.parent')->when($status === 'open', fn ($q) => $q->open(), fn ($q) => $q->whereNotNull('resolved_at'))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderByRaw("FIELD(severity, 'error', 'warning', 'info')")->orderByDesc('period_start')->orderBy('id')->paginate(30)->withQueryString();

        return view('quality-flags', [
            'title' => 'Data quality', 'subtitle' => 'Problems found in reports and records', 'flags' => $flags, 'counts' => $counts, 'status' => $status, 'type' => $type,
            'area' => $area, 'areaOptions' => $this->areaOptions($user), 'canReview' => $user->can('review data quality'),
        ]);
    }

    public function review(Request $request, DataQualityFlag $flag): RedirectResponse
    {
        abort_unless($request->user()->canAccessOrgUnit($flag->orgUnit), 403);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']]);
        $flag->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id, 'resolution_note' => $data['note'] ?? null]);
        Audit::log(Audit::CONFIG, 'data_quality_flag_reviewed', $flag, ['type' => $flag->type]);

        return back()->with('status', 'Marked as reviewed.');
    }
}
