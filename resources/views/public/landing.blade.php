@extends('layouts.public')
@section('title', 'Disease surveillance for Uganda')
@section('content')
    <section class="ow-hero" aria-labelledby="hero-h">
        <h1 id="hero-h">Early warning for notifiable diseases</h1>
        <p class="lead">District Outbreak Watch brings weekly counts, vaccination coverage, reporting completeness, alerts and projections into one secure place for district and national health teams.</p>
        <p>@auth<a class="btn btn-primary btn-lg" href="{{ route('dashboard') }}">Go to the dashboard</a>@else<a class="btn btn-primary btn-lg" href="{{ route('login') }}">Sign in</a>@endauth
            <a class="btn btn-default btn-lg" href="{{ route('situation') }}">National situation</a></p>
    </section>

    @if ($week)
    <section aria-labelledby="snap-h">
        <h2 id="snap-h">Last full week: week starting {{ $week->format('d F Y') }}</h2>
        <div class="row">
            @foreach ($rows as $r)
                <div class="col-sm-6 col-md-4">
                    <div class="ow-stat2"><div class="s-label">{{ $r->disease->name }}</div><div class="s-num">{{ number_format($r->cases) }}</div>
                        <div class="s-hint">cases nationally @if ($r->delta !== null)· {{ $r->delta > 0 ? 'up' : 'down' }} {{ abs($r->delta) }}% on the week before @endif</div></div>
                </div>
            @endforeach
        </div>
        <p class="text-muted">National totals from sentinel facilities. @if (config('surveillance.synthetic_data'))These figures are generated sample data, not real surveillance results. @endif <a href="{{ route('about') }}">See the data sources</a>.</p>
    </section>
    @endif

    <section aria-labelledby="what-h">
        <h2 id="what-h">What staff can do after signing in</h2>
        <div class="row">
            <div class="col-md-4"><h3>See and compare</h3><p>Weekly trends, a district map, a quality control chart, vaccination coverage and reporting completeness, limited to the area you are responsible for.</p></div>
            <div class="col-md-4"><h3>Act early</h3><p>Alerts when a count rises above its normal range, escalation when nobody responds, and short projections with their uncertainty.</p></div>
            <div class="col-md-4"><h3>Protect people</h3><p>Role-based access, an audit trail of every case view, and no downloads of case-level records.</p></div>
        </div>
    </section>
@endsection
