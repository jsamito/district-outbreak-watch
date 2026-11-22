<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\Alert;
use App\Models\Disease;
use App\Services\AggregateQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Small JSON summary used by the dashboard for live updates and as the polling fallback. */
class LiveController extends Controller
{
    use ResolvesFilters;

    public function summary(Request $request, AggregateQuery $q): JsonResponse
    {
        $area = $this->area($request);
        $week = $q->currentWeek();
        $diseases = Disease::orderBy('priority')->orderBy('name')->get()->map(fn (Disease $d) => [
            'code' => $d->code, 'live' => (int) ($q->series($area, $d->id, $week, $week)[$week->toDateString()] ?? 0),
        ]);

        return response()->json([
            'week' => $week->toDateString(), 'diseases' => $diseases, 'open_alerts' => Alert::visibleTo($request->user())->where('status', 'open')->count(),
        ])->header('Cache-Control', 'no-store');
    }
}
