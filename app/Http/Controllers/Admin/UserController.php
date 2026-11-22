<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrgUnit;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users', ['title' => 'Users', 'users' => User::with('roles', 'orgUnits')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return $this->form(new User(['active' => true]), 'New user');
    }

    public function edit(User $user): View
    {
        return $this->form($user, 'Edit user');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $password = Str::password(14, symbols: false);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'job_title' => $data['job_title'] ?? null, 'password' => $password, 'active' => true, 'must_change_password' => true]);
        $this->assign($user, $data);
        Audit::log(Audit::CONFIG, 'user_created', $user, ['role' => $data['role'], 'areas' => $user->orgUnits->pluck('code')]);

        return redirect()->route('admin.users')->with('status', "User created. Temporary password (shown once): $password");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        $user->update(['name' => $data['name'], 'email' => $data['email'], 'job_title' => $data['job_title'] ?? null, 'active' => $request->boolean('active')]);
        $this->assign($user, $data);
        $note = '';
        if ($request->boolean('reset_password')) {
            $password = Str::password(14, symbols: false);
            $user->update(['password' => $password, 'must_change_password' => true]);
            $note = " New temporary password (shown once): $password";
        }
        if ($user->id === $request->user()->id && ! $user->active) {
            $user->update(['active' => true]);
        }
        Audit::log(Audit::CONFIG, 'user_updated', $user, ['role' => $data['role'], 'active' => $user->active, 'areas' => $user->orgUnits->pluck('code')]);

        return redirect()->route('admin.users')->with('status', 'User saved.'.$note);
    }

    private function form(User $user, string $title): View
    {
        return view('admin.user-form', [
            'title' => $title, 'user' => $user, 'roles' => array_keys(RolesAndPermissionsSeeder::ROLES),
            'areas' => OrgUnit::whereIn('type', ['country', 'region', 'district'])->orderBy('level')->orderBy('name')->get(['id', 'name', 'type']),
            'facilities' => OrgUnit::where('type', 'facility')->whereIn('id', fn ($q) => $q->select('org_unit_id')->from('facility_profiles')->where('sentinel', true))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:160', 'unique:users,email'.($user ? ','.$user->id : '')],
            'job_title' => ['nullable', 'string', 'max:80'], 'role' => ['required', 'in:'.implode(',', array_keys(RolesAndPermissionsSeeder::ROLES))],
            'areas' => ['required', 'array', 'min:1'], 'areas.*' => ['exists:org_units,id'],
        ]);
    }

    private function assign(User $user, array $data): void
    {
        $user->syncRoles([$data['role']]);
        $user->orgUnits()->sync($data['areas']);
    }
}
