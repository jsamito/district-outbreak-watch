@extends('layouts.app', ['title' => 'Notify a case', 'breadcrumbs' => ['Cases' => route('cases.index'), 'Notify a case' => '#']])

@section('content')
    <form method="POST" action="{{ route('cases.store') }}" class="box" novalidate>
        @csrf
        <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-plus-square-o" aria-hidden="true"></i> New notification</h3></div>
        <div class="box-body">
            @if ($errors->any())<div class="alert alert-danger" role="alert"><strong>Please correct the following:</strong><ul style="margin:6px 0 0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
            <p class="text-muted">Do not enter names, addresses or phone numbers. Only the details below are needed.</p>
            <div class="row">
                <div class="col-sm-6 form-group @error('disease') has-error @enderror"><label for="disease">Disease</label>
                    <select id="disease" name="disease" class="form-control" required><option value="">Select disease</option>@foreach ($diseases as $d)<option value="{{ $d->code }}" @selected(old('disease') === $d->code)>{{ $d->name }} ({{ $d->icd10_code }})</option>@endforeach</select></div>
                <div class="col-sm-6 form-group @error('facility_code') has-error @enderror"><label for="facility_code">Reporting facility</label>
                    <select id="facility_code" name="facility_code" class="form-control ow-select2" required>@foreach ($facilities as $f)<option value="{{ $f->code }}" @selected(old('facility_code') === $f->code || $facilities->count() === 1)>{{ $f->name }}</option>@endforeach</select></div>
            </div>
            <div class="row">
                <div class="col-sm-3 form-group @error('sex') has-error @enderror"><label for="sex">Sex</label><select id="sex" name="sex" class="form-control" required><option value="F" @selected(old('sex') === 'F')>Female</option><option value="M" @selected(old('sex') === 'M')>Male</option><option value="U" @selected(old('sex') === 'U')>Unknown</option></select></div>
                <div class="col-sm-3 form-group @error('age_years') has-error @enderror"><label for="age_years">Age in years</label><input id="age_years" name="age_years" type="number" min="0" max="120" inputmode="numeric" class="form-control" value="{{ old('age_years') }}" required></div>
                <div class="col-sm-3 form-group @error('onset_date') has-error @enderror"><label for="onset_date">Symptom onset</label><input id="onset_date" name="onset_date" type="date" max="{{ $today }}" class="form-control" value="{{ old('onset_date') }}" required></div>
                <div class="col-sm-3 form-group @error('notification_date') has-error @enderror"><label for="notification_date">Notification date</label><input id="notification_date" name="notification_date" type="date" max="{{ $today }}" class="form-control" value="{{ old('notification_date', $today) }}" required></div>
            </div>
            <div class="row">
                <div class="col-sm-4 form-group"><label for="classification">Classification</label><select id="classification" name="classification" class="form-control"><option value="suspected">Suspected</option><option value="probable" @selected(old('classification') === 'probable')>Probable</option><option value="confirmed" @selected(old('classification') === 'confirmed')>Confirmed</option></select></div>
                <div class="col-sm-8 form-group"><label for="notes">Clinical notes (optional)</label><input id="notes" name="notes" maxlength="500" class="form-control" value="{{ old('notes') }}"></div>
            </div>
            <div class="checkbox"><label><input type="checkbox" name="hospitalised" value="1" @checked(old('hospitalised'))> Admitted to hospital</label></div>
        </div>
        <div class="box-footer"><button class="btn btn-primary" type="submit"><i class="fa fa-paper-plane" aria-hidden="true"></i> Send notification</button> <a class="btn btn-default" href="{{ route('cases.index') }}">Cancel</a></div>
    </form>
@endsection
