@extends('layouts.app', ['title' => 'Data quality'])
@section('subtitle', $subtitle)

@section('content')
    <section aria-label="Open problems by kind" class="row">
        @foreach (\App\Models\DataQualityFlag::TYPES as $key => [$label, $what, $why])
            <div class="col-sm-6 col-md-4">
                <a class="ow-stat2 {{ ($counts[$key] ?? 0) ? 'is-alert' : '' }}" href="{{ route('quality-flags.index', array_filter(['type' => $key, 'area' => $area?->id])) }}">
                    <div class="s-label">{{ $label }}</div><div class="s-num">{{ number_format($counts[$key] ?? 0) }}</div><div class="s-hint">{{ $why }}</div>
                </a>
            </div>
        @endforeach
    </section>

    <form method="GET" action="{{ route('quality-flags.index') }}" class="ow-filters box" aria-label="Filter data-quality flags">
        <div class="box-body"><div class="row">
            <div class="col-sm-6 col-md-3 form-group"><label for="q-area">Area</label>
                <select id="q-area" name="area" class="form-control ow-select2" data-allow-clear="true" data-placeholder="All my areas"><option value=""></option>@foreach ($areaOptions as $o)<option value="{{ $o->id }}" @selected($area?->id === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
            <div class="col-sm-6 col-md-3 form-group"><label for="q-type">Kind of problem</label>
                <select id="q-type" name="type" class="form-control"><option value="">All kinds</option>@foreach (\App\Models\DataQualityFlag::TYPES as $k => $t)<option value="{{ $k }}" @selected($type === $k)>{{ $t[0] }}</option>@endforeach</select></div>
            <div class="col-sm-6 col-md-2 form-group"><label for="q-status">Show</label>
                <select id="q-status" name="status" class="form-control"><option value="open" @selected($status === 'open')>Open problems</option><option value="reviewed" @selected($status === 'reviewed')>Reviewed or closed</option></select></div>
            <div class="ow-filter-actions"><button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-filter" aria-hidden="true"></i> Apply filters</button><a class="btn btn-default btn-sm" href="{{ route('quality-flags.index') }}">Reset</a></div>
        </div></div>
    </form>

    <div class="box">
        <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-flag-o" aria-hidden="true"></i> {{ number_format($flags->total()) }} {{ $status === 'open' ? 'open problems' : 'reviewed or closed' }}</h2></div>
        <div class="box-body no-padding">
            <table class="table table-hover">
                <caption class="sr-only">Data-quality flags</caption>
                <thead><tr><th scope="col">Problem</th><th scope="col">Where</th><th scope="col">Week or month</th><th scope="col">Detail</th><th scope="col">{{ $status === 'open' ? 'Review' : 'Outcome' }}</th></tr></thead>
                <tbody>
                @forelse ($flags as $f)
                    <tr>
                        <td><span class="badge badge-{{ ['error' => 'cancelled', 'warning' => 'supply', 'info' => 'scheduled'][$f->severity] ?? 'muted' }}">{{ ucfirst($f->severity === 'error' ? 'blocks reports' : ($f->severity === 'warning' ? 'check' : 'note')) }}</span> {{ $f->typeLabel() }}</td>
                        <td>{{ $f->orgUnit->name }}@if ($f->orgUnit->type === 'facility' && $f->orgUnit->parent)<br><small class="text-muted">{{ $f->orgUnit->parent->name }}</small>@endif</td>
                        <td>{{ $f->period_start?->format('d M Y') }}</td>
                        <td>{{ $f->detail }}</td>
                        <td>
                            @if ($status === 'open')
                                @if ($canReview)
                                    <form method="POST" action="{{ route('quality-flags.review', $f) }}" class="form-inline" style="display:flex;gap:6px">
                                        @csrf
                                        <label class="sr-only" for="note-{{ $f->id }}">Note for {{ $f->typeLabel() }} at {{ $f->orgUnit->name }}</label>
                                        <input id="note-{{ $f->id }}" name="note" class="form-control input-sm" maxlength="300" placeholder="What you found (optional)">
                                        <button class="btn btn-default btn-sm" type="submit">Mark reviewed</button>
                                    </form>
                                @else<span class="text-muted">Open</span>@endif
                            @else
                                {{ $f->resolver ? 'Reviewed by '.$f->resolver->name : 'Closed automatically' }}@if ($f->resolution_note)<br><small class="text-muted">{{ $f->resolution_note }}</small>@endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="ow-empty"><i class="fa fa-check-circle text-green" aria-hidden="true"></i><p>Nothing to review here.</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="box-footer clearfix">{{ $flags->links() }}</div>
    </div>
    <p class="text-muted"><small>Checks run every morning. A problem that disappears is closed by the system; one you review stays reviewed. Flags do not change any figure on the dashboards: they tell you which numbers to treat with care.</small></p>
@endsection
