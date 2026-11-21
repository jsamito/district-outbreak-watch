<?php

namespace App\Http\Controllers;

use App\Models\OrgUnit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrgUnitController extends Controller
{
    public function show(Request $request, OrgUnit $orgUnit): View
    {
        $this->authorize('view', $orgUnit);

        $children = $orgUnit->children()->orderBy('name')->limit(200)->get();

        return view('org-units.show', [
            'unit' => $orgUnit,
            'ancestors' => $this->ancestors($orgUnit),
            'children' => $children->filter(fn (OrgUnit $c) => $request->user()->canAccessOrgUnit($c)),
            'childCount' => $orgUnit->children()->count(),
        ]);
    }

    /** @return array<int, OrgUnit> only ancestors the viewer may see */
    protected function ancestors(OrgUnit $unit): array
    {
        $list = [];
        $user = request()->user();
        for ($p = $unit->parent; $p; $p = $p->parent) {
            if ($user->canAccessOrgUnit($p)) {
                array_unshift($list, $p);
            }
        }

        return $list;
    }
}
