<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Models\Disease;
use App\Models\OrgUnit;
use App\Support\Audit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertRuleController extends Controller
{
    public function index(): View
    {
        return view('alert-rules.index', ['title' => 'Alert rules', 'rules' => AlertRule::with('disease', 'orgUnit')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return $this->form(new AlertRule(['method' => 'outbreak_signal', 'severity' => 'auto', 'min_count' => 5, 'cooldown_hours' => 336, 'escalate_after_minutes' => 720,
            'audience_roles' => ['district_officer'], 'params' => ['baseline_weeks' => 8, 'guard_weeks' => 1, 'sigmas' => 3], 'enabled' => true]), 'New alert rule');
    }

    public function edit(AlertRule $alertRule): View
    {
        return $this->form($alertRule, 'Edit alert rule');
    }

    public function store(Request $request): RedirectResponse
    {
        $rule = AlertRule::create($this->validated($request));
        Audit::log(Audit::CONFIG, 'alert_rule_created', $rule, ['name' => $rule->name]);

        return redirect()->route('alert-rules.index')->with('status', 'Alert rule created.');
    }

    public function update(Request $request, AlertRule $alertRule): RedirectResponse
    {
        $alertRule->update($this->validated($request));
        Audit::log(Audit::CONFIG, 'alert_rule_updated', $alertRule, ['name' => $alertRule->name]);

        return redirect()->route('alert-rules.index')->with('status', 'Alert rule saved.');
    }

    private function form(AlertRule $rule, string $title): View
    {
        return view('alert-rules.form', [
            'title' => $title, 'rule' => $rule, 'diseases' => Disease::orderBy('name')->get(),
            'areas' => OrgUnit::whereIn('type', ['region', 'district'])->orderBy('level')->orderBy('name')->get(['id', 'name', 'type']),
            'roles' => array_keys(RolesAndPermissionsSeeder::ROLES), 'methods' => AlertRule::METHODS,
        ]);
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'disease_id' => ['nullable', 'exists:diseases,id'], 'org_unit_id' => ['nullable', 'exists:org_units,id'],
            'method' => ['required', 'in:'.implode(',', array_keys(AlertRule::METHODS))], 'severity' => ['required', 'in:auto,high,medium,low'],
            'min_count' => ['required', 'integer', 'min:1', 'max:1000'], 'cooldown_hours' => ['required', 'integer', 'min:1', 'max:2160'],
            'escalate_after_minutes' => ['nullable', 'integer', 'min:15', 'max:10080'], 'baseline_weeks' => ['required', 'integer', 'min:4', 'max:26'],
            'sigmas' => ['required', 'numeric', 'min:1', 'max:6'], 'audience_roles' => ['required', 'array', 'min:1'], 'audience_roles.*' => ['in:'.implode(',', array_keys(RolesAndPermissionsSeeder::ROLES))],
        ]);

        return [
            'name' => $d['name'], 'disease_id' => $d['disease_id'] ?? null, 'org_unit_id' => $d['org_unit_id'] ?? null, 'method' => $d['method'], 'severity' => $d['severity'],
            'min_count' => $d['min_count'], 'cooldown_hours' => $d['cooldown_hours'], 'escalate_after_minutes' => $d['escalate_after_minutes'] ?? null,
            'params' => ['baseline_weeks' => (int) $d['baseline_weeks'], 'guard_weeks' => 1, 'sigmas' => (float) $d['sigmas']],
            'audience_roles' => array_values($d['audience_roles']), 'enabled' => $request->boolean('enabled'),
        ];
    }
}
