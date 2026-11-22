@extends('layouts.app', ['title' => 'Export data'])

@section('content')
    <div class="ow-notice"><i class="fa fa-lock" aria-hidden="true"></i> Exports contain <strong>aggregated counts only</strong>. Individual case records can never be exported. Every export is recorded in the audit log.</div>

    <form method="GET" action="{{ route('exports.download') }}" class="ow-filters box" aria-label="Export options">
        <div class="box-body">
            <div class="row">
                <div class="col-sm-6 col-md-3 form-group">
                    <label for="f-area">Area</label>
                    <select id="f-area" name="area" class="form-control ow-select2">
                        @foreach ($areaOptions as $o)<option value="{{ $o->id }}" @selected($o->id == $filters['area'])>{{ $o->name }}@if ($o->type === 'region') region @endif</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-2 form-group">
                    <label for="f-disease">Disease</label>
                    <select id="f-disease" name="disease" class="form-control ow-select2">@foreach ($diseases as $d)<option value="{{ $d->code }}" @selected($d->code === $filters['disease'])>{{ $d->name }}</option>@endforeach</select>
                </div>
                {!! $extra !!}
                <div class="col-xs-6 col-md-2 form-group"><label for="f-from">From</label><input id="f-from" type="text" name="from" class="form-control ow-datepicker" value="{{ $filters['from'] }}" autocomplete="off"></div>
                <div class="col-xs-6 col-md-2 form-group"><label for="f-to">To</label><input id="f-to" type="text" name="to" class="form-control ow-datepicker" value="{{ $filters['to'] }}" autocomplete="off"></div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa fa-download" aria-hidden="true"></i> Download CSV</button>
        </div>
    </form>

    <div class="box"><div class="box-header with-border"><h3 class="box-title">What the files contain</h3></div>
        <div class="box-body">
            <ul>
                <li><strong>Weekly cases:</strong> area, disease with ICD-10 code, week, cases, population, incidence per 100,000 and a small-number flag.</li>
                <li><strong>Reporting completeness:</strong> reports received and expected per week.</li>
                <li><strong>Vaccination coverage:</strong> doses, target population and coverage per month, with a flag for values above 100%.</li>
            </ul>
            <p class="text-muted">Files begin with comment lines that state the source, the period and the small-number rule.</p>
        </div>
    </div>
@endsection
