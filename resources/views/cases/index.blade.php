@extends('layouts.app', ['title' => 'Cases'])
@section('subtitle', $full ? 'Line list' : 'Pseudonymised line list')

@section('content')
    <div class="ow-notice"><i class="fa fa-lock" aria-hidden="true"></i>
        @if (config('surveillance.synthetic_data'))<strong>Synthetic records.</strong> @endif
        {{ $full ? 'You see full case detail for your area.' : 'You see pseudonymised records: no facility name, exact dates or ages.' }} Every time you open this list or a case, it is recorded in the audit log. Case records cannot be exported or downloaded.</div>

    <form method="GET" action="{{ route('cases.index') }}" class="ow-filters box" aria-label="Filter cases">
        <div class="box-body">
            <div class="row">
                <div class="col-sm-6 col-md-3 form-group"><label for="c-disease">Disease</label>
                    <select id="c-disease" name="disease" class="form-control"><option value="">All</option>@foreach ($diseases as $d)<option value="{{ $d->code }}" @selected(($f['disease'] ?? '') === $d->code)>{{ $d->name }}</option>@endforeach</select></div>
                <div class="col-sm-6 col-md-2 form-group"><label for="c-class">Classification</label>
                    <select id="c-class" name="classification" class="form-control"><option value="">All</option>@foreach (['suspected', 'probable', 'confirmed'] as $c)<option value="{{ $c }}" @selected(($f['classification'] ?? '') === $c)>{{ ucfirst($c) }}</option>@endforeach</select></div>
                <div class="col-sm-6 col-md-2 form-group"><label for="c-status">Investigation</label>
                    <select id="c-status" name="status" class="form-control"><option value="">All</option>@foreach (['reported' => 'Reported', 'investigating' => 'Investigating', 'closed' => 'Closed'] as $k => $l)<option value="{{ $k }}" @selected(($f['status'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-sm-6 col-md-3 form-group"><label for="c-area">Area</label>
                    <select id="c-area" name="area" class="form-control ow-select2" data-allow-clear="true" data-placeholder="All my areas"><option value=""></option>@foreach ($areaOptions as $o)<option value="{{ $o->id }}" @selected(($f['area'] ?? '') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
                <div class="col-xs-6 col-md-1 form-group"><label for="c-from">From</label><input id="c-from" name="from" type="text" class="form-control ow-datepicker" value="{{ $f['from'] ?? '' }}" autocomplete="off"></div>
                <div class="col-xs-6 col-md-1 form-group"><label for="c-to">To</label><input id="c-to" name="to" type="text" class="form-control ow-datepicker" value="{{ $f['to'] ?? '' }}" autocomplete="off"></div>
            </div>
            <div class="ow-filter-actions"><button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-filter" aria-hidden="true"></i> Apply filters</button><a class="btn btn-default btn-sm" href="{{ route('cases.index') }}">Reset</a></div>
        </div>
    </form>

    <div class="box">
        <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-user-o" aria-hidden="true"></i> {{ number_format($cases->total()) }} cases</h3></div>
        <div class="box-body no-padding">
            <table class="table table-hover">
                <caption class="sr-only">Cases</caption>
                <thead><tr><th scope="col">Case</th><th scope="col">Disease</th>@if ($full)<th scope="col">Facility</th><th scope="col">Notified</th><th scope="col">Age</th>@else<th scope="col">District</th><th scope="col">Week of</th><th scope="col">Age group</th>@endif<th scope="col">Sex</th><th scope="col">Classification</th><th scope="col">Outcome</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Open</span></th></tr></thead>
                <tbody>
                @forelse ($cases as $c)
                    <tr>
                        <td><code>{{ \Illuminate\Support\Str::substr($c->person->pseudonym, 0, 8) }}</code></td>
                        <td>{{ $c->disease->name }}</td>
                        @if ($full)
                            <td>{{ $c->facility->name }}</td><td>{{ $c->notification_date->format('d M Y') }}</td><td>{{ $c->person->ageOn($c->notification_date) }}</td>
                        @else
                            <td>{{ $c->residence->name ?? '' }}</td><td>{{ $c->epi_week->format('d M Y') }}</td><td>{{ \App\Models\Person::ageGroup($c->person->ageOn($c->notification_date)) }}</td>
                        @endif
                        <td>{{ $c->person->sex }}</td>
                        <td><span class="badge badge-{{ ['confirmed' => 'completed', 'probable' => 'supply', 'suspected' => 'scheduled'][$c->classification] ?? 'muted' }}">{{ ucfirst($c->classification) }}</span></td>
                        <td>{{ $c->outcome === 'died' ? 'Died' : ucfirst($c->outcome) }}</td>
                        <td>{{ ucfirst($c->status) }}</td>
                        <td class="text-right"><a class="btn btn-default btn-xs" style="width:auto" href="{{ route('cases.show', $c) }}">Open<span class="sr-only"> case {{ \Illuminate\Support\Str::substr($c->person->pseudonym, 0, 8) }}</span></a></td>
                    </tr>
                @empty
                    <tr><td colspan="10"><div class="ow-empty"><p>No cases match these filters.</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="box-footer clearfix">{{ $cases->links() }}</div>
    </div>
@endsection
