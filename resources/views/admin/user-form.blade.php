@extends('layouts.app', ['title' => $title, 'breadcrumbs' => ['Users' => route('admin.users'), $title => '#']])
@php
    $roleHelp = [
        'super_admin' => 'Full access to every screen and every area: users, rules, API clients, data quality, cases, labs, alerts and exports.',
        'system_admin' => 'Manages users, data sources, API clients and alert rules. Sees no case records.',
        'national_manager' => 'Sees every area: charts, alerts, forecasts and exports. Acknowledges alerts and manages rules.',
        'regional_officer' => 'Sees charts, forecasts, alerts and pseudonymised case lists for every district in the assigned region. Acknowledges alerts.',
        'epidemiologist' => 'Sees charts, forecasts and pseudonymised case lists (no names, facilities or exact dates).',
        'district_officer' => 'Sees full case detail in the assigned district, notifies cases, enters lab results, acknowledges alerts.',
        'clinician' => 'Notifies and updates cases in the assigned facility or district and receives alerts.',
        'lab_officer' => 'Sees only the lab worklist and enters test results.',
    ];
    $chosen = old('role', $user->getRoleNames()->first() ?? 'district_officer');
    $selectedAreas = old('areas', $user->exists ? $user->orgUnits->pluck('id')->all() : []);
@endphp
@section('content')
    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="ow-form" novalidate>
        @csrf @if ($user->exists) @method('PUT') @endif
        @if ($errors->any())<div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div>@endif

        <section class="box ow-form__section" aria-labelledby="u1">
            <div class="box-header with-border"><h2 class="box-title" id="u1"><span class="ow-step">1</span> Who is this person</h2></div>
            <div class="box-body"><div class="ow-fields">
                <div class="form-group @error('name') has-error @enderror"><label for="name">Full name</label><input id="name" name="name" class="form-control" value="{{ old('name', $user->name) }}" required autocomplete="off">@error('name')<span class="help-block">{{ $message }}</span>@enderror</div>
                <div class="form-group @error('email') has-error @enderror"><label for="email">Email address</label><input id="email" type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required autocomplete="off">@error('email')<span class="help-block">{{ $message }}</span>@enderror</div>
                <div class="form-group"><label for="job_title">Job title <span class="text-muted">(optional)</span></label><input id="job_title" name="job_title" class="form-control" value="{{ old('job_title', $user->job_title) }}"></div>
            </div></div>
        </section>

        <section class="box ow-form__section" aria-labelledby="u2">
            <div class="box-header with-border"><h2 class="box-title" id="u2"><span class="ow-step">2</span> What they can see</h2></div>
            <div class="box-body">
                <fieldset class="ow-methods @error('role') has-error @enderror">
                    <legend class="sr-only">Role</legend>
                    @foreach ($roles as $r)
                        <label class="ow-method"><input type="radio" name="role" value="{{ $r }}" @checked($chosen === $r) required><span class="ow-method__body"><strong>{{ ucfirst(str_replace('_', ' ', $r)) }}</strong><small>{{ $roleHelp[$r] ?? '' }}</small></span></label>
                    @endforeach
                    @error('role')<span class="help-block text-danger">{{ $message }}</span>@enderror
                </fieldset>
                <div class="form-group @error('areas') has-error @enderror">
                    <label for="areas">Area(s) of responsibility</label>
                    <select id="areas" name="areas[]" class="form-control ow-select2" multiple required data-placeholder="Choose one or more areas" style="min-height:36px">
                        <optgroup label="Country, regions and districts">@foreach ($areas as $a)<option value="{{ $a->id }}" @selected(in_array($a->id, $selectedAreas))>{{ $a->name }} ({{ $a->type }})</option>@endforeach</optgroup>
                        <optgroup label="Reporting facilities">@foreach ($facilities as $f)<option value="{{ $f->id }}" @selected(in_array($f->id, $selectedAreas))>{{ $f->name }}</option>@endforeach</optgroup>
                    </select>
                    <span class="help-block">The person sees only data inside these areas and everything below them. National roles are assigned to the country.</span>@error('areas')<span class="help-block">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <section class="box ow-form__section" aria-labelledby="u3">
            <div class="box-header with-border"><h2 class="box-title" id="u3"><span class="ow-step">3</span> Account</h2></div>
            <div class="box-body">
                @if ($user->exists)
                    <div class="checkbox"><label><input type="checkbox" name="active" value="1" @checked(old('active', $user->active))> <strong>Account is active</strong></label></div>
                    <div class="checkbox"><label><input type="checkbox" name="reset_password" value="1"> Generate a new temporary password</label></div>
                @else
                    <p class="text-muted" style="margin:0">A temporary password is generated and shown once after saving. Ask the person to change it at first sign-in.</p>
                @endif
            </div>
            <div class="box-footer"><button class="btn btn-primary" type="submit">Save user</button> <a class="btn btn-default" href="{{ route('admin.users') }}">Cancel</a></div>
        </section>
    </form>
@endsection

@push('scripts')
<script @nonce>document.querySelectorAll('.ow-method input').forEach(function (i) { function s() { document.querySelectorAll('.ow-method').forEach(function (l) { l.classList.toggle('is-on', l.querySelector('input').checked); }); } i.addEventListener('change', s); s(); });</script>
@endpush
