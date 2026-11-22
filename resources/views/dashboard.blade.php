@extends('layouts.app', ['title' => 'Overview'])

@section('subtitle', $area->name.' · latest week starting '.$latest->format('d M Y'))

@section('actions')
    <form method="GET" action="{{ route('dashboard') }}" class="form-inline" aria-label="Choose area">
        <input type="hidden" name="disease" value="{{ $disease->code }}">
        <label for="ov-area" class="sr-only">Area</label>
        <select id="ov-area" name="area" class="form-control input-sm" onchange="this.form.submit()">
            @foreach ($areaChoices as $o)
                <option value="{{ $o->id }}" @selected($o->id == $area->id)>{{ $o->name }}@if ($o->type === 'region') region @endif</option>
            @endforeach
        </select>
        <noscript><button class="btn btn-default btn-sm" type="submit">Go</button></noscript>
    </form>
@endsection

@section('content')
    <section aria-label="Headline numbers" class="ow-strip">
        @foreach ($strip as $s)
            <div>
                <div class="s-label"><i class="fa fa-stethoscope" aria-hidden="true"></i> {{ $s->disease->name }}</div>
                <div class="s-num">{{ number_format($s->cases) }}
                    @if ($s->delta !== null)
                        <span class="s-delta {{ $s->delta > 0 ? 'bad' : 'good' }}"><span aria-hidden="true">{{ $s->delta > 0 ? '▲' : '▼' }}</span> {{ abs($s->delta) }}%<span class="sr-only"> {{ $s->delta > 0 ? 'increase' : 'decrease' }} on the previous week</span></span>
                    @endif
                </div>
                <div class="s-hint">cases this week @if ($s->cases > 0 && $s->cases < config('surveillance.small_number_floor'))· <span class="ow-flag">small number</span>@endif</div>
            </div>
        @endforeach
        <div>
            <div class="s-label"><i class="fa fa-check-square-o" aria-hidden="true"></i> Reporting</div>
            <div class="s-num">{{ $reporting !== null ? number_format($reporting, 0).'%' : 'n/a' }}</div>
            <div class="s-hint">of expected weekly reports received</div>
        </div>
    </section>

    <div class="ow-period" role="group" aria-label="Disease shown in the charts">
        @foreach ($diseases as $d)
            <a href="{{ route('dashboard', ['disease' => $d->code, 'area' => $area->id]) }}" class="btn btn-sm {{ $d->id === $disease->id ? 'btn-primary' : 'btn-default' }}" @if ($d->id === $disease->id) aria-current="true" @endif>{{ $d->name }}</a>
        @endforeach
    </div>

    <div class="row ow-eq">
        <div class="col-md-8">
            <x-chart-card id="chart-trend" title="{{ $disease->name }}: weekly cases, last 52 weeks" icon="fa-line-chart" :height="300"
                :summary="'Weekly cases of '.$disease->name.' in '.$area->name.'. Diamonds mark weeks above the outbreak signal threshold (baseline mean plus three standard deviations, at least '.config('surveillance.small_number_floor').' cases). The axis starts at zero.'">
                <x-slot:actions><a href="{{ route('trends', ['disease' => $disease->code, 'area' => $area->id]) }}" class="btn btn-box-tool">Open trends</a></x-slot:actions>
                <x-slot:table>
                    <table class="table table-condensed"><thead><tr><th>Week starting</th><th class="text-right">Cases</th><th>Signal</th></tr></thead><tbody>
                        @foreach (array_reverse(array_keys($trend['labels'])) as $i)
                            <tr><td>{{ $trend['labels'][$i] }}</td><td class="text-right">{{ number_format($trend['cases'][$i]) }}</td><td>{{ $trend['signals'][$i] ? 'Above threshold' : '' }}</td></tr>
                        @endforeach
                    </tbody></table>
                </x-slot:table>
            </x-chart-card>
        </div>
        <div class="col-md-4">
            <div class="box">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-bell-o" aria-hidden="true"></i> Needs attention <span class="badge badge-muted">{{ $attention->count() }}</span></h3>
                    @if (Route::has('alerts.index'))<div class="box-tools pull-right"><a href="{{ route('alerts.index') }}" class="btn btn-box-tool">All alerts</a></div>@endif</div>
                @if ($attention->isEmpty())
                    <div class="ow-empty" style="padding:28px"><i class="fa fa-check-circle text-green" aria-hidden="true"></i><p>No area is above its outbreak threshold this week.</p></div>
                @else
                    <ul class="ow-attn">
                        @foreach ($attention as $a)
                            <li><a href="{{ route('trends', ['disease' => \Illuminate\Support\Str::slug($a->disease), 'area' => $a->unit_id]) }}">
                                <span class="a-ic"><i class="fa {{ $a->severity === 'high' ? 'fa-exclamation-circle' : 'fa-line-chart' }}" aria-hidden="true"></i></span>
                                <span class="a-main">{{ $a->disease }} · {{ $a->unit }}
                                    <small>@if ($a->immediate) Immediately notifiable: {{ number_format($a->observed) }} case(s) @else {{ number_format($a->observed) }} cases against {{ number_format($a->expected, 1) }} expected @endif</small></span>
                                <span class="ow-sig sev-{{ $a->severity }}">{{ ucfirst($a->severity) }}</span></a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="row ow-eq">
        <div class="col-md-6">
            <x-chart-card id="chart-top" :title="$disease->name.': '.($rank === 'incidence' ? 'highest incidence' : 'most cases').' in the last 4 weeks'" icon="fa-bar-chart" :height="300"
                :summary="'Areas below '.$area->name.' ranked by '.($rank === 'incidence' ? 'cases per 100,000 people' : 'number of cases').' over the last four weeks. The axis starts at zero.'">
                <x-slot:table>
                    <table class="table table-condensed"><thead><tr><th>Area</th><th class="text-right">Cases</th><th class="text-right">Population</th><th class="text-right">Per 100,000</th></tr></thead><tbody>
                        @foreach ($top as $t)<tr><td>{{ $t->name }}</td><td class="text-right">{{ number_format($t->cases) }}</td><td class="text-right">{{ $t->population ? number_format($t->population) : 'n/a' }}</td><td class="text-right">{{ $t->incidence !== null ? number_format($t->incidence, 1) : 'n/a' }}</td></tr>@endforeach
                    </tbody></table>
                </x-slot:table>
            </x-chart-card>
        </div>
        <div class="col-md-6">
            <x-chart-card id="chart-reporting" title="Reporting completeness, last 26 weeks" icon="fa-check-square-o" :height="300"
                summary="Share of expected weekly reports received from reporting facilities. Higher is better; the axis runs from 0 to 100 percent.">
            </x-chart-card>
        </div>
    </div>

    <div class="row ow-eq">
        <div class="col-md-12">
            <x-chart-card id="chart-vacc" title="Vaccination coverage, last 12 months" icon="fa-medkit" :height="260"
                summary="Doses given divided by the expected number of children under one year, by vaccine, against the target. Values above 100% indicate a data-quality problem (numerator or population).">
                <x-slot:actions><a href="{{ route('vaccination', ['area' => $area->id]) }}" class="btn btn-box-tool">Open vaccination</a></x-slot:actions>
            </x-chart-card>
        </div>
    </div>
@endsection

@push('plugins')
<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
<script src="{{ asset('js/charts.js') }}"></script>
@endpush

@push('scripts')
<script @nonce>
(function () {
    var floor = @js(config('surveillance.small_number_floor'));
    owCharts.line('chart-trend', { labels: @js($trend['labels']), yTitle: 'Cases per week', floor: floor, ariaLabel: @js('Weekly cases of '.$disease->name),
        series: [{ label: 'Weekly cases', data: @js($trend['cases']), signals: @js($trend['signals']) },
                 { label: 'Signal threshold', data: @js($trend['upper']), color: '#6b7682', dashed: true }], points: true });
    owCharts.bar('chart-top', { horizontal: true, labels: @js($top->pluck('name')), yTitle: @js($rank === 'incidence' ? 'Cases per 100,000 people' : 'Cases'), integer: @js($rank !== 'incidence'), floor: floor,
        ariaLabel: @js('Areas ranked by '.$rank), series: [{ label: @js($rank === 'incidence' ? 'Per 100,000' : 'Cases'), data: @js($top->pluck($rank)->map(fn ($v) => round($v, 1))->values()) }] });
    owCharts.line('chart-reporting', { labels: @js(array_keys($reportTrend)), yTitle: 'Reports received (%)', yMax: 100, integer: false, unit: '%', ariaLabel: 'Reporting completeness by week',
        series: [{ label: 'Completeness', data: @js(array_map(fn ($v) => round($v, 1), array_values($reportTrend))) }] });
    var vacc = @js($vacc);
    owCharts.bar('chart-vacc', { labels: vacc.map(function (v) { return v.code; }), yTitle: 'Coverage (%)', yMax: 110, integer: false, unit: '%', ariaLabel: 'Vaccination coverage by vaccine',
        series: [{ label: 'Coverage', data: vacc.map(function (v) { return v.coverage; }) },
                 { label: 'Target', data: vacc.map(function (v) { return v.target; }), type: 'line', color: '#d55e00', order: 0 }] });
}());
</script>
@endpush
