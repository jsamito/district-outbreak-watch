<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFilters;
use App\Models\Alert;
use App\Models\AlertPreference;
use App\Models\Disease;
use App\Services\AggregateQuery;
use App\Services\OutbreakDetector;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    use ResolvesFilters;

    public function __construct(private AggregateQuery $q) {}

    public function index(Request $request): View
    {
        $status = in_array($request->input('status'), ['active', 'resolved', 'all'], true) ? $request->input('status') : 'active';
        $query = Alert::visibleTo($request->user())->with('disease', 'orgUnit', 'rule', 'acknowledger');
        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'resolved') {
            $query->where('status', 'resolved');
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }
        if ($request->filled('disease')) {
            $query->whereHas('disease', fn ($q) => $q->where('code', $request->input('disease')));
        }
        $alerts = $query->orderByRaw("FIELD(severity,'high','medium','low')")->orderByDesc('last_signal_week')->limit(500)->get();

        return view('alerts.index', [
            'title' => 'Alerts', 'alerts' => $alerts, 'status' => $status, 'diseases' => Disease::orderBy('name')->get(),
            'counts' => ['active' => Alert::visibleTo($request->user())->active()->count(), 'resolved' => Alert::visibleTo($request->user())->where('status', 'resolved')->count()],
            'preference' => AlertPreference::find($request->user()->id)?->mode ?? 'auto',
        ]);
    }

    public function show(Request $request, Alert $alert): View
    {
        $this->authorize('view', $alert);
        $alert->load('disease', 'orgUnit', 'rule', 'acknowledger', 'deliveries.user');
        $to = $alert->last_signal_week->toImmutable();
        $series = $this->q->series($alert->orgUnit, $alert->disease_id, $to->subWeeks(34), $to);
        $eval = (new OutbreakDetector)->evaluate($series);
        $weeks = array_values(array_filter(array_keys($series), fn ($w) => $w >= $to->subWeeks(25)->toDateString()));

        return view('alerts.show', [
            'title' => ucfirst($alert->severity).' alert', 'subtitle' => $alert->disease->name.' · '.$alert->orgUnit->name, 'alert' => $alert,
            'labels' => $weeks, 'cases' => array_map(fn ($w) => $series[$w], $weeks), 'upper' => array_map(fn ($w) => $eval[$w]['upper'] ?? null, $weeks),
            'signals' => array_map(fn ($w) => (bool) ($eval[$w]['signal'] ?? false), $weeks),
            'canAcknowledge' => $request->user()->can('acknowledge', $alert),
            'seeRecipients' => $request->user()->hasAnyRole(['national_manager', 'system_admin']),
        ]);
    }

    public function acknowledge(Request $request, Alert $alert): RedirectResponse
    {
        $this->authorize('acknowledge', $alert);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $alert->update(['status' => 'acknowledged', 'acknowledged_by' => $request->user()->id, 'acknowledged_at' => now(), 'note' => $data['note'] ?? null]);
        Audit::log(Audit::DATA_ACCESS, 'alert_acknowledged', $alert, ['disease' => $alert->disease->code, 'area' => $alert->orgUnit->code]);

        return redirect()->route('alerts.show', $alert)->with('status', 'Alert acknowledged.');
    }

    public function preferences(Request $request): RedirectResponse
    {
        $data = $request->validate(['mode' => ['required', 'in:auto,digest,off']]);
        AlertPreference::updateOrCreate(['user_id' => $request->user()->id], ['mode' => $data['mode']]);
        Audit::log(Audit::CONFIG, 'alert_preference', null, ['mode' => $data['mode']]);

        return back()->with('status', 'Notification preference saved.');
    }
}
