<?php

namespace App\Http\Controllers;

use App\Models\OrgUnit;
use App\Models\SavedView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SavedViewController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'], 'route' => ['required', 'in:'.implode(',', array_keys(SavedView::ROUTES))],
            'area' => ['nullable', 'integer', 'exists:org_units,id'], 'facility' => ['nullable', 'integer', 'exists:org_units,id'], 'disease' => ['nullable', 'exists:diseases,code'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);
        $user = $request->user();
        foreach (['area', 'facility'] as $k) {
            if (! empty($data[$k])) {
                abort_unless($user->canAccessOrgUnit(OrgUnit::findOrFail($data[$k])), 403);
            }
        }
        if (SavedView::where('user_id', $user->id)->count() >= SavedView::MAX_PER_USER) {
            return back()->withErrors(['name' => 'You can keep up to '.SavedView::MAX_PER_USER.' saved views. Remove one first.']);
        }
        $filters = array_filter(['area' => $data['area'] ?? null, 'facility' => $data['facility'] ?? null, 'disease' => $data['disease'] ?? null]);
        if ($request->boolean('keep_dates')) {
            $filters += array_filter(['from' => $data['from'] ?? null, 'to' => $data['to'] ?? null]);
        }
        SavedView::create(['user_id' => $user->id, 'name' => $data['name'], 'route' => $data['route'], 'filters' => $filters]);

        return back()->with('status', 'View saved.');
    }

    public function destroy(Request $request, SavedView $savedView): RedirectResponse
    {
        abort_unless($savedView->user_id === $request->user()->id, 403);
        $savedView->delete();

        return back()->with('status', 'Saved view removed.');
    }
}
