@extends('layouts.app', ['title' => $title, 'breadcrumbs' => ['Alert rules' => route('alert-rules.index'), $title => '#']])

@php
    $methodInfo = [
        'outbreak_signal' => ['Outbreak signal', 'Cases this week are well above the recent normal for the area.'],
        'threshold' => ['Fixed threshold', 'Cases in a week reach a number you choose.'],
        'cluster' => ['Cluster', 'Several cases are notified close together in time in one district.'],
        'single_case' => ['Single case', 'Any case of a disease that must be acted on at once.'],
    ];
    $current = old('method', $rule->method);
@endphp

@section('content')
    <form method="POST" action="{{ $rule->exists ? route('alert-rules.update', $rule) : route('alert-rules.store') }}" class="ow-form" id="rule-form" novalidate>
        @csrf @if ($rule->exists) @method('PUT') @endif
        @if ($errors->any())<div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div>@endif

        <section class="box ow-form__section" aria-labelledby="s1">
            <div class="box-header with-border"><h2 class="box-title" id="s1"><span class="ow-step">1</span> What to watch</h2></div>
            <div class="box-body">
                <div class="form-group @error('name') has-error @enderror">
                    <label for="name">Rule name</label>
                    <input id="name" name="name" class="form-control" value="{{ old('name', $rule->name) }}" required maxlength="160" placeholder="e.g. Cholera cluster in Kasese">
                    @error('name')<span class="help-block">{{ $message }}</span>@enderror
                </div>
                <div class="ow-fields">
                    <div class="form-group"><label for="disease_id">Disease</label>
                        <select id="disease_id" name="disease_id" class="form-control ow-select2"><option value="">All matching diseases</option>@foreach ($diseases as $d)<option value="{{ $d->id }}" @selected(old('disease_id', $rule->disease_id) == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
                    <div class="form-group"><label for="org_unit_id">Area</label>
                        <select id="org_unit_id" name="org_unit_id" class="form-control ow-select2"><option value="">Whole country</option>@foreach ($areas as $a)<option value="{{ $a->id }}" @selected(old('org_unit_id', $rule->org_unit_id) == $a->id)>{{ $a->name }} ({{ $a->type }})</option>@endforeach</select></div>
                </div>
            </div>
        </section>

        <section class="box ow-form__section" aria-labelledby="s2">
            <div class="box-header with-border"><h2 class="box-title" id="s2"><span class="ow-step">2</span> When to raise an alert</h2></div>
            <div class="box-body">
                <fieldset class="ow-methods">
                    <legend class="sr-only">Method</legend>
                    @foreach ($methodInfo as $key => [$label, $text])
                        <label class="ow-method"><input type="radio" name="method" value="{{ $key }}" @checked($current === $key)><span class="ow-method__body"><strong>{{ $label }}</strong><small>{{ $text }}</small></span></label>
                    @endforeach
                    @error('method')<span class="help-block text-danger">{{ $message }}</span>@enderror
                </fieldset>

                <div class="ow-fields ow-fields--tight">
                    <div class="form-group @error('min_count') has-error @enderror" data-methods="outbreak_signal threshold cluster">
                        <label for="min_count"><span data-only="cluster">Cases needed</span><span data-only="outbreak_signal threshold">Smallest count that can alert</span></label>
                        <input id="min_count" type="number" min="1" name="min_count" class="form-control" value="{{ old('min_count', $rule->min_count) }}">
                        <span class="help-block">Stops alerts from tiny numbers.</span>@error('min_count')<span class="help-block">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group" data-methods="outbreak_signal">
                        <label for="baseline_weeks">Baseline (weeks)</label>
                        <input id="baseline_weeks" type="number" min="4" max="26" name="baseline_weeks" class="form-control" value="{{ old('baseline_weeks', $rule->params['baseline_weeks'] ?? 8) }}">
                        <span class="help-block">Recent weeks that define "normal".</span>
                    </div>
                    <div class="form-group" data-methods="outbreak_signal">
                        <label for="sigmas">Allowed rise (std. deviations)</label>
                        <input id="sigmas" type="number" step="0.5" min="1" max="6" name="sigmas" class="form-control" value="{{ old('sigmas', $rule->params['sigmas'] ?? 3) }}">
                        <span class="help-block">Higher means fewer alerts.</span>
                    </div>
                    <div class="form-group @error('threshold') has-error @enderror" data-methods="threshold">
                        <label for="threshold">Cases in a week</label>
                        <input id="threshold" type="number" min="1" name="threshold" class="form-control" value="{{ old('threshold', $rule->params['threshold'] ?? 10) }}">
                        <span class="help-block">Alert when a district reaches this many.</span>@error('threshold')<span class="help-block">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group @error('cluster_days') has-error @enderror" data-methods="cluster">
                        <label for="cluster_days">Within (days)</label>
                        <input id="cluster_days" type="number" min="3" max="60" name="cluster_days" class="form-control" value="{{ old('cluster_days', $rule->params['cluster_days'] ?? 14) }}">
                        <span class="help-block">All the cases must be notified in this time.</span>@error('cluster_days')<span class="help-block">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="box ow-form__section" aria-labelledby="s3">
            <div class="box-header with-border"><h2 class="box-title" id="s3"><span class="ow-step">3</span> Who is told, and how often</h2></div>
            <div class="box-body">
                <fieldset class="form-group @error('audience_roles') has-error @enderror">
                    <legend class="ow-legend">Told first</legend>
                    @foreach ($roles as $role)
                        @continue(in_array($role, ['system_admin', 'lab_officer', 'epidemiologist']))
                        <label class="checkbox-inline"><input type="checkbox" name="audience_roles[]" value="{{ $role }}" @checked(in_array($role, old('audience_roles', $rule->audience_roles ?? [])))> {{ ucfirst(str_replace('_', ' ', $role)) }}</label>
                    @endforeach
                    <span class="help-block">Only people whose area covers the alert are told. If nobody responds, national managers are told next.</span>
                    @error('audience_roles')<span class="help-block">{{ $message }}</span>@enderror
                </fieldset>
                <div class="ow-fields ow-fields--tight">
                    <div class="form-group"><label for="severity">Severity</label>
                        <select id="severity" name="severity" class="form-control">@foreach (['auto' => 'Automatic', 'high' => 'Always high', 'medium' => 'Always medium', 'low' => 'Always low'] as $k => $l)<option value="{{ $k }}" @selected(old('severity', $rule->severity) === $k)>{{ $l }}</option>@endforeach</select>
                        <span class="help-block">Automatic grades by the size of the rise. High alerts are emailed at once; others join the daily summary.</span></div>
                    <div class="form-group"><label for="cooldown_hours">Quiet period (hours)</label><input id="cooldown_hours" type="number" min="1" name="cooldown_hours" class="form-control" value="{{ old('cooldown_hours', $rule->cooldown_hours) }}">
                        <span class="help-block">An alert that returns within this time is reopened, not repeated.</span></div>
                    <div class="form-group"><label for="escalate_after_minutes">Escalate after (minutes)</label><input id="escalate_after_minutes" type="number" min="15" name="escalate_after_minutes" class="form-control" value="{{ old('escalate_after_minutes', $rule->escalate_after_minutes) }}">
                        <span class="help-block">Leave empty to never escalate.</span></div>
                </div>
                <div class="checkbox"><label><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $rule->enabled))> <strong>Rule is on</strong></label></div>
            </div>
            <div class="box-footer"><button class="btn btn-primary" type="submit">Save rule</button> <a class="btn btn-default" href="{{ route('alert-rules.index') }}">Cancel</a></div>
        </section>
    </form>
@endsection

@push('scripts')
<script src="{{ asset_ver('js/rule-form.js') }}"></script>
@endpush
