<?php

namespace App\Http\Controllers;

use App\Models\Annotation;
use App\Models\Disease;
use App\Models\OrgUnit;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnnotationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'org_unit_id' => ['required', 'integer', 'exists:org_units,id'], 'disease' => ['nullable', 'exists:diseases,code'],
            'period_start' => ['required', 'date'], 'text' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $unit = OrgUnit::findOrFail($data['org_unit_id']);
        abort_unless($request->user()->canAccessOrgUnit($unit), 403);

        $note = Annotation::create([
            'user_id' => $request->user()->id, 'org_unit_id' => $unit->id, 'disease_id' => ! empty($data['disease']) ? Disease::where('code', $data['disease'])->value('id') : null,
            'period_start' => CarbonImmutable::parse($data['period_start'])->startOfWeek()->toDateString(), 'text' => $data['text'],
        ]);
        Audit::log(Audit::CONFIG, 'annotation_created', $note, ['area' => $unit->id]);

        return back()->with('status', 'Note added.');
    }

    public function destroy(Request $request, Annotation $annotation): RedirectResponse
    {
        abort_unless($annotation->user_id === $request->user()->id, 403);
        $annotation->delete();

        return back()->with('status', 'Note removed.');
    }
}
