<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\Antigen;
use App\Models\Disease;
use App\Models\OrgUnit;
use App\Services\AggregateQuery;
use App\Services\OutbreakDetector;
use App\Services\SignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesFilters;

    public function __construct(private AggregateQuery $q, private OutbreakDetector $detector, private SignalService $signals) {}

    /** Disease-dependent chart data: weekly trend with signals and the areas ranked below the selected one. */
    private function panel(OrgUnit $area, Disease $disease, \Carbon\CarbonImmutable $latest): array
    {
        $from = $latest->subWeeks(51);
        $series = $this->q->series($area, $disease->id, $from->subWeeks(11), $latest);
        $eval = $this->detector->evaluate($series);
        $weeks = array_values(array_filter(array_keys($series), fn ($w) => $w >= $from->toDateString()));
        $trend = [
            'labels' => $weeks,
            'cases' => array_map(fn ($w) => $series[$w], $weeks),
            'signals' => array_map(fn ($w) => (bool) ($eval[$w]['signal'] ?? false), $weeks),
            'upper' => array_map(fn ($w) => $eval[$w]['upper'] ?? null, $weeks),
        ];
        $children = $this->q->childTotals($area, $disease->id, $latest->subWeeks(3), $latest);
        $rank = $children->every(fn ($c) => $c->incidence !== null) && $children->isNotEmpty() ? 'incidence' : 'cases';

        return ['trend' => $trend, 'top' => $children->sortByDesc($rank)->take(10)->values(), 'rank' => $rank];
    }

    /** JSON for the overview charts when the disease is switched without reloading the page. */
    public function data(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('view aggregates'), 403);
        $area = $this->area($request);
        $latest = $this->q->latestWeek();
        abort_unless($latest, 404);
        $disease = $this->disease($request, 'measles');
        $p = $this->panel($area, $disease, $latest);

        return response()->json([
            'disease' => ['code' => $disease->code, 'name' => $disease->name],
            'trend' => $p['trend'], 'rank' => $p['rank'],
            'top' => $p['top']->map(fn ($t) => ['name' => $t->name, 'cases' => (float) $t->cases, 'population' => $t->population, 'incidence' => $t->incidence === null ? null : round($t->incidence, 1)])->values(),
            'trendsUrl' => route('trends', ['disease' => $disease->code, 'area' => $area->id]),
        ])->header('Cache-Control', 'no-store');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user->can('view aggregates')) {
            return view('dashboard-basic', [
                'user' => $user,
                'roots' => $user->orgUnits()->orderBy('level')->orderBy('name')->get(),
                'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values(),
            ]);
        }

        $area = $this->area($request);
        $latest = $this->q->latestWeek();
        abort_unless($latest, 404, 'No surveillance data has been loaded yet.');
        $prev = $latest->subWeek();
        $disease = $this->disease($request, 'measles');
        $diseases = Disease::orderBy('priority')->orderBy('name')->get();

        $strip = $diseases->map(function (Disease $d) use ($area, $latest, $prev) {
            $s = $this->q->series($area, $d->id, $prev, $latest);
            $cur = $s[$latest->toDateString()] ?? 0.0;
            $pre = $s[$prev->toDateString()] ?? 0.0;

            $live = $this->q->series($area, $d->id, $this->q->currentWeek(), $this->q->currentWeek())[$this->q->currentWeek()->toDateString()] ?? 0.0;

            return (object) ['disease' => $d, 'cases' => $cur, 'prev' => $pre, 'live' => $live, 'delta' => $pre > 0 ? round(($cur - $pre) / $pre * 100) : null];
        });
        $reporting = $this->q->reportingSeries($area, $latest, $latest)[$latest->toDateString()] ?? null;

        $panel = $this->panel($area, $disease, $latest);
        $trend = $panel['trend'];
        $top = $panel['top'];
        $rank = $panel['rank'];

        $reportTrend = $this->q->reportingSeries($area, $latest->subWeeks(25), $latest);

        $antigens = Antigen::orderBy('id')->get();
        $cov = $this->q->vaccinationByAntigen($area, $latest->subMonths(11)->startOfMonth(), $latest);
        $vacc = $antigens->map(fn (Antigen $a) => ['code' => $a->code, 'name' => $a->name, 'target' => (float) $a->target_percent, 'coverage' => isset($cov[$a->id]['coverage']) ? round($cov[$a->id]['coverage'], 1) : null]);

        return view('dashboard', [
            'title' => 'Overview', 'area' => $area, 'latest' => $latest, 'disease' => $disease, 'diseases' => $diseases,
            'strip' => $strip, 'reporting' => $reporting, 'trend' => $trend, 'top' => $top, 'rank' => $rank,
            'reportTrend' => $reportTrend, 'vacc' => $vacc,
            'attention' => $this->signals->current($user, $latest)->take(8),
            'areaChoices' => $this->areaOptions($user), 'isFacility' => $area->type === OrgUnit::FACILITY,
        ]);
    }
}
