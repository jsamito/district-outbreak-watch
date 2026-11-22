<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\OrgUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataController extends Controller
{
    use ResolvesFilters;

    /**
     * District polygons. Districts the user may see carry their name and id; every other district is sent as plain
     * outline (no name, no id) so the map still reads as Uganda without exposing anything outside the user's area.
     */
    public function districts(Request $request): JsonResponse
    {
        $area = $this->area($request);
        $root = $area->type === OrgUnit::FACILITY ? $area->parent : $area;
        $user = $request->user();
        $features = OrgUnit::where('type', OrgUnit::DISTRICT)->whereNotNull('geometry')->orderBy('name')->get(['id', 'name', 'code', 'path', 'geometry'])
            ->map(function ($d) use ($user, $root) {
                $inside = ($d->path === $root->path || str_starts_with($d->path, $root->path.'/')) && $user->canAccessOrgUnit($d);

                return ['type' => 'Feature', 'properties' => $inside ? ['id' => $d->id, 'name' => $d->name, 'code' => $d->code, 'accessible' => true] : ['accessible' => false], 'geometry' => $d->geometry];
            })->values();

        return response()->json(['type' => 'FeatureCollection', 'features' => $features])->header('Cache-Control', 'private, max-age=600');
    }
}
