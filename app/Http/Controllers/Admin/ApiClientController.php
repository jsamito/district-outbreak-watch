<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\IngestEvent;
use App\Models\OrgUnit;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Facility systems allowed to send notifications to the API. A token is shown once and only its hash is kept. */
class ApiClientController extends Controller
{
    public function index(): View
    {
        $stats = IngestEvent::selectRaw("api_client_id, SUM(status = 'applied') applied, SUM(status = 'rejected') rejected, MAX(received_at) last_at")->groupBy('api_client_id')->get()->keyBy('api_client_id');
        $areas = OrgUnit::where('type', OrgUnit::DISTRICT)->orWhere(fn ($q) => $q->where('type', OrgUnit::FACILITY)->whereIn('id', fn ($s) => $s->select('org_unit_id')->from('facility_profiles')->where('sentinel', true)))
            ->orderBy('name')->get(['id', 'name', 'type']);

        return view('admin.api-clients', ['title' => 'API clients', 'subtitle' => 'Facility systems that send notifications', 'clients' => ApiClient::with('orgUnit')->orderBy('name')->get(), 'stats' => $stats, 'areas' => $areas]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'org_unit_id' => ['required', 'exists:org_units,id']]);
        [$client, $token] = ApiClient::issue($data['name'], OrgUnit::findOrFail($data['org_unit_id']));
        Audit::log(Audit::CONFIG, 'api_client_issued', $client, ['area' => $data['org_unit_id']]);

        return redirect()->route('admin.api-clients')->with('new_token', $token)->with('new_token_name', $client->name);
    }

    public function toggle(ApiClient $client): RedirectResponse
    {
        $client->update(['active' => ! $client->active]);
        Audit::log(Audit::CONFIG, $client->active ? 'api_client_enabled' : 'api_client_disabled', $client);

        return back()->with('status', $client->active ? 'Client enabled.' : 'Client disabled: its token no longer works.');
    }
}
