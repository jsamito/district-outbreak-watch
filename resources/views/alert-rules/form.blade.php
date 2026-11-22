@extends('layouts.app', ['title' => $title, 'breadcrumbs' => ['Alert rules' => route('alert-rules.index'), $title => '#']])

@section('content')
    <form method="POST" action="{{ $rule->exists ? route('alert-rules.update', $rule) : route('alert-rules.store') }}" class="box" novalidate>
        @csrf @if ($rule->exists) @method('PUT') @endif
        <div class="box-body">
            @if ($errors->any())<div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div>@endif
            <div class="row">
                <div class="col-md-6 form-group @error('name') has-error @enderror"><label for="name">Rule name</label><input id="name" name="name" class="form-control" value="{{ old('name', $rule->name) }}" required>@error('name')<span class="help-block">{{ $message }}</span>@enderror</div>
                <div class="col-md-3 form-group"><label for="method">Method</label>
                    <select id="method" name="method" class="form-control">@foreach ($methods as $k => $l)<option value="{{ $k }}" @selected(old('method', $rule->method) === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-md-3 form-group"><label for="severity">Severity</label>
                    <select id="severity" name="severity" class="form-control">@foreach (['auto' => 'Automatic (by size of rise)', 'high' => 'Always high', 'medium' => 'Always medium', 'low' => 'Always low'] as $k => $l)<option value="{{ $k }}" @selected(old('severity', $rule->severity) === $k)>{{ $l }}</option>@endforeach</select></div>
            </div>
            <div class="row">
                <div class="col-md-4 form-group"><label for="disease_id">Disease</label>
                    <select id="disease_id" name="disease_id" class="form-control ow-select2"><option value="">All matching diseases</option>@foreach ($diseases as $d)<option value="{{ $d->id }}" @selected(old('disease_id', $rule->disease_id) == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
                <div class="col-md-4 form-group"><label for="org_unit_id">Area</label>
                    <select id="org_unit_id" name="org_unit_id" class="form-control ow-select2"><option value="">All areas</option>@foreach ($areas as $a)<option value="{{ $a->id }}" @selected(old('org_unit_id', $rule->org_unit_id) == $a->id)>{{ $a->name }} ({{ $a->type }})</option>@endforeach</select></div>
                <div class="col-md-4 form-group"><label for="min_count">Minimum cases in the week</label><input id="min_count" type="number" min="1" name="min_count" class="form-control" value="{{ old('min_count', $rule->min_count) }}"><span class="help-block">Prevents alerts from tiny numbers.</span></div>
            </div>
            <div class="row">
                <div class="col-md-3 form-group"><label for="baseline_weeks">Baseline weeks</label><input id="baseline_weeks" type="number" min="4" max="26" name="baseline_weeks" class="form-control" value="{{ old('baseline_weeks', $rule->params['baseline_weeks'] ?? 8) }}"></div>
                <div class="col-md-3 form-group"><label for="sigmas">Standard deviations above baseline</label><input id="sigmas" type="number" step="0.5" min="1" max="6" name="sigmas" class="form-control" value="{{ old('sigmas', $rule->params['sigmas'] ?? 3) }}"></div>
                <div class="col-md-3 form-group"><label for="cooldown_hours">Cooldown (hours)</label><input id="cooldown_hours" type="number" min="1" name="cooldown_hours" class="form-control" value="{{ old('cooldown_hours', $rule->cooldown_hours) }}"><span class="help-block">A resolved alert that signals again within this time is reopened, not repeated.</span></div>
                <div class="col-md-3 form-group"><label for="escalate_after_minutes">Escalate after (minutes)</label><input id="escalate_after_minutes" type="number" min="15" name="escalate_after_minutes" class="form-control" value="{{ old('escalate_after_minutes', $rule->escalate_after_minutes) }}"><span class="help-block">Leave empty to never escalate.</span></div>
            </div>
            <div class="row">
                <div class="col-md-3 form-group @error('threshold') has-error @enderror"><label for="threshold">Threshold (cases in a week)</label><input id="threshold" type="number" min="1" name="threshold" class="form-control" value="{{ old('threshold', $rule->params['threshold'] ?? 10) }}"><span class="help-block">Used by the fixed threshold method.</span>@error('threshold')<span class="help-block">{{ $message }}</span>@enderror</div>
                <div class="col-md-3 form-group @error('cluster_days') has-error @enderror"><label for="cluster_days">Cluster window (days)</label><input id="cluster_days" type="number" min="3" max="60" name="cluster_days" class="form-control" value="{{ old('cluster_days', $rule->params['cluster_days'] ?? 14) }}"><span class="help-block">Used by the cluster method: the minimum cases above must fall within this many days in one district.</span>@error('cluster_days')<span class="help-block">{{ $message }}</span>@enderror</div>
            </div>
            <fieldset class="form-group @error('audience_roles') has-error @enderror"><legend style="font-size:13px;font-weight:600;border:0;margin-bottom:6px">Who is notified first</legend>
                @foreach ($roles as $role)
                    @continue(in_array($role, ['system_admin', 'lab_officer', 'epidemiologist']))
                    <label class="checkbox-inline"><input type="checkbox" name="audience_roles[]" value="{{ $role }}" @checked(in_array($role, old('audience_roles', $rule->audience_roles ?? [])))> {{ str_replace('_', ' ', $role) }}</label>
                @endforeach
                <span class="help-block">Only people whose area covers the alert are notified. Escalation goes to national managers.</span>
                @error('audience_roles')<span class="help-block">{{ $message }}</span>@enderror
            </fieldset>
            <div class="checkbox"><label><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $rule->enabled))> Rule is on</label></div>
        </div>
        <div class="box-footer"><button class="btn btn-primary" type="submit">Save rule</button> <a class="btn btn-default" href="{{ route('alert-rules.index') }}">Cancel</a></div>
    </form>
@endsection
