<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\OrgUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataController extends Controller
{
    use ResolvesFilters;

    /** District polygons limited to the districts the signed-in user may see. */
    public function districts(Request $request): JsonResponse
    {
        $area = $this->area($request);
        $root = $area->type === OrgUnit::FACILITY ? $area->parent : $area;
        $features = OrgUnit::within($root)->where('type', OrgUnit::DISTRICT)->whereNotNull('geometry')->orderBy('name')->get(['id', 'name', 'code', 'path', 'geometry'])
            ->filter(fn ($d) => $request->user()->canAccessOrgUnit($d))
            ->map(fn ($d) => ['type' => 'Feature', 'properties' => ['id' => $d->id, 'name' => $d->name, 'code' => $d->code], 'geometry' => $d->geometry])->values();

        return response()->json(['type' => 'FeatureCollection', 'features' => $features])->header('Cache-Control', 'private, max-age=600');
    }
}
