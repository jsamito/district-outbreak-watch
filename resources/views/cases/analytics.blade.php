@extends('layouts.app', ['title' => 'Case analytics', 'breadcrumbs' => ['Cases' => route('cases.index'), 'Analytics' => '#']])
@section('subtitle', $subtitle)

@section('content')
    <div class="ow-notice"><i class="fa fa-lock" aria-hidden="true"></i>
        @if (config('surveillance.synthetic_data'))<strong>Synthetic records.</strong> @endif
        These charts count cases and show no names, case numbers or exact dates of birth. Viewing them is recorded in the audit log.
        Groups with fewer than {{ $floor }} cases are marked, because small numbers move a lot from one week to the next.</div>

    @include('partials.filters')

    @if ($totals['total'] === 0)
        <div class="box"><div class="box-body"><div class="ow-empty"><i class="fa fa-user-o" aria-hidden="true"></i><p>No cases were notified in this area and period. Try a wider date range or another area.</p></div></div></div>
    @else
    <section aria-label="Case summary" class="row">
        <div class="col-sm-6 col-md-3"><div class="ow-stat2"><div class="s-label">Cases notified</div><div class="s-num">{{ number_format($totals['total']) }}</div><div class="s-hint">{{ number_format($totals['confirmed']) }} confirmed · {{ number_format($totals['hospitalised']) }} admitted</div></div></div>
        @php $cfrAll = $totals['total'] ? round($totals['deaths'] / $totals['total'] * 100, 1) : null; @endphp
        <div class="col-sm-6 col-md-3"><div class="ow-stat2"><div class="s-label">Deaths</div><div class="s-num">{{ number_format($totals['deaths']) }}</div><div class="s-hint">{{ $cfrAll }}% of {{ number_format($totals['total']) }} cases @if ($totals['total'] < $floor)· <span class="ow-flag">small number</span>@endif</div></div></div>
        <div class="col-sm-6 col-md-3"><div class="ow-stat2"><div class="s-label">Median days, onset to notification</div><div class="s-num">{{ $delay['median'] !== null ? rtrim(rtrim(number_format($delay['median'], 1), '0'), '.') : 'n/a' }}</div><div class="s-hint">{{ $delay['within_3_days'] }}% notified within 3 days</div></div></div>
        @php $tested = collect($lab['rows'])->sum('tested'); @endphp
        <div class="col-sm-6 col-md-3"><div class="ow-stat2"><div class="s-label">Cases with a finished lab test</div><div class="s-num">{{ $totals['total'] ? round($tested / $totals['total'] * 100) : 0 }}%</div><div class="s-hint">{{ number_format($tested) }} of {{ number_format($totals['total']) }} cases @if ($lab['median_turnaround'] !== null)· median {{ rtrim(rtrim(number_format($lab['median_turnaround'], 1), '0'), '.') }} days to result @endif</div></div></div>
    </section>

    <x-chart-card id="chart-epi" title="Epidemic curve: cases by date of symptom onset" icon="fa-bar-chart" :height="300"
        :summary="'Number of cases by '.$curve['unit'].' of symptom onset, stacked by classification (confirmed, probable, suspected). '.number_format($curve['total']).' cases. The axis starts at zero.'">
        <x-slot:table>
            <table class="table table-condensed"><caption class="sr-only">Cases by {{ $curve['unit'] }} of onset</caption><thead><tr><th scope="col">{{ ucfirst($curve['unit']) }} starting</th>@foreach ($curve['series'] as $s)<th scope="col" class="text-right">{{ $s['label'] }}</th>@endforeach</tr></thead><tbody>
                @foreach (array_reverse(array_keys($curve['labels'])) as $i)
                    <tr><td>{{ $curve['labels'][$i] }}</td>@foreach ($curve['series'] as $s)<td class="text-right">{{ $s['data'][$i] }}</td>@endforeach</tr>
                @endforeach
            </tbody></table>
        </x-slot:table>
    </x-chart-card>

    <div class="row ow-eq">
        <div class="col-md-6">
            <x-chart-card id="chart-pyramid" title="Age and sex" icon="fa-users" :height="300"
                :summary="'Cases by age band: males to the left, females to the right. '.number_format($pyramid['total']).' cases'.($pyramid['unknown_sex'] ? ', of which '.$pyramid['unknown_sex'].' have no sex recorded and are not drawn' : '').'.'">
                <x-slot:table>
                    <table class="table table-condensed"><caption class="sr-only">Cases by age band and sex</caption><thead><tr><th scope="col">Age band (years)</th><th scope="col" class="text-right">Male</th><th scope="col" class="text-right">Female</th></tr></thead><tbody>
                        @foreach ($pyramid['bands'] as $i => $b)<tr><td>{{ $b }}</td><td class="text-right">{{ $pyramid['male'][$i] }}</td><td class="text-right">{{ $pyramid['female'][$i] }}</td></tr>@endforeach
                    </tbody></table>
                </x-slot:table>
            </x-chart-card>
        </div>
        <div class="col-md-6">
            <x-chart-card id="chart-delay" title="Time from onset to notification" icon="fa-clock-o" :height="300"
                :summary="'Number of cases by days between first symptoms and notification. Median '.($delay['median'] ?? 'unknown').' days. Faster notification gives teams more time to respond.'">
                <x-slot:table>
                    <table class="table table-condensed"><caption class="sr-only">Cases by days from onset to notification</caption><thead><tr><th scope="col">Days</th><th scope="col" class="text-right">Cases</th></tr></thead><tbody>
                        @foreach ($delay['bands'] as $i => $b)<tr><td>{{ $b }}</td><td class="text-right">{{ number_format($delay['counts'][$i]) }}</td></tr>@endforeach
                    </tbody></table>
                </x-slot:table>
            </x-chart-card>
        </div>
    </div>

    <div class="box">
        <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-heartbeat" aria-hidden="true"></i> Case fatality by disease</h2></div>
        <div class="box-body no-padding">
            <table class="table table-hover" id="cfr-table">
                <caption class="sr-only">Deaths as a share of cases, by disease, with a 95% interval</caption>
                <thead><tr><th scope="col">Disease</th><th scope="col" class="text-right">Cases</th><th scope="col" class="text-right">Deaths</th><th scope="col" class="text-right">Outcome unknown</th><th scope="col" class="text-right">Fatality</th><th scope="col" class="text-right">95% interval</th></tr></thead>
                <tbody>
                @foreach ($fatality as $r)
                    <tr><th scope="row">{{ $r['disease'] }}</th><td class="text-right">{{ number_format($r['cases']) }}</td><td class="text-right">{{ number_format($r['deaths']) }}</td><td class="text-right">{{ number_format($r['unknown']) }}</td>
                        <td class="text-right"><strong>{{ $r['cfr'] }}%</strong> @if ($r['cases'] < $floor)<span class="ow-flag">small number</span>@endif</td><td class="text-right">{{ $r['low'] }}% to {{ $r['high'] }}%</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="box-footer"><small class="text-muted">Fatality is deaths divided by all notified cases. Cases whose outcome is not yet known stay in the total, so recent outbreaks look milder than they are until outcomes arrive. The interval shows how far the true figure could be from the one measured.</small></div>
    </div>

    <x-chart-card id="chart-lab" title="Laboratory follow-up by disease" icon="fa-flask" :height="300"
        :summary="'Share of notified cases with a finished laboratory test, and share of those tests that were positive. The numbers behind each bar are in the table.'">
        <x-slot:table>
            <table class="table table-condensed"><caption class="sr-only">Laboratory follow-up by disease</caption><thead><tr><th scope="col">Disease</th><th scope="col" class="text-right">Cases</th><th scope="col" class="text-right">With a finished test</th><th scope="col" class="text-right">Positive</th><th scope="col" class="text-right">Test pending</th></tr></thead><tbody>
                @foreach ($lab['rows'] as $r)<tr><th scope="row">{{ $r['disease'] }}</th><td class="text-right">{{ number_format($r['cases']) }}</td><td class="text-right">{{ number_format($r['tested']) }} ({{ $r['tested_pct'] }}%)</td><td class="text-right">{{ number_format($r['positive']) }} @if ($r['positive_pct'] !== null)({{ $r['positive_pct'] }}% of tested)@endif</td><td class="text-right">{{ number_format($r['pending']) }}</td></tr>@endforeach
            </tbody></table>
        </x-slot:table>
    </x-chart-card>
    @endif
@endsection

@if ($totals['total'] > 0)
@push('plugins')
<script src="{{ asset_ver('vendor/chartjs/chart.umd.js') }}"></script>
<script src="{{ asset_ver('js/charts.js') }}"></script>
@endpush
@push('scripts')
<script @nonce>
(function () {
    var curve = @js($curve), pyr = @js($pyramid), delay = @js($delay), lab = @js($lab['rows']), floor = @js($floor);
    var classColors = { Confirmed: '#0072b2', Probable: '#e69f00', Suspected: '#a8b3bf' };
    owCharts.lazy('chart-epi', function () {
        owCharts.bar('chart-epi', { labels: curve.labels, stacked: true, yTitle: 'Cases per ' + curve.unit, categoryTitle: curve.unit === 'week' ? 'Week starting' : 'Date of onset', autoSkip: true,
            ariaLabel: 'Epidemic curve by date of symptom onset', series: curve.series.map(function (s) { return { label: s.label, data: s.data, color: classColors[s.label] }; }) });
    });
    var maxP = Math.max.apply(null, pyr.male.concat(pyr.female).concat([1]));
    owCharts.lazy('chart-pyramid', function () {
        owCharts.bar('chart-pyramid', { labels: pyr.bands, horizontal: true, stacked: true, absTicks: true, symmetric: Math.ceil(maxP * 1.1), yTitle: 'Cases (males left, females right)', categoryTitle: 'Age (years)',
            ariaLabel: 'Cases by age band and sex', floor: floor, series: [{ label: 'Male', data: pyr.male.map(function (v) { return -v; }), color: '#0072b2' }, { label: 'Female', data: pyr.female, color: '#d55e00' }] });
    });
    owCharts.lazy('chart-delay', function () {
        owCharts.bar('chart-delay', { labels: delay.bands, yTitle: 'Cases', categoryTitle: 'Days from onset to notification', ariaLabel: 'Cases by days from onset to notification', floor: floor,
            series: [{ label: 'Cases', data: delay.counts, color: '#0072b2' }] });
    });
    owCharts.lazy('chart-lab', function () {
        owCharts.bar('chart-lab', { labels: lab.map(function (r) { return r.disease; }), yTitle: 'Per cent', yMax: 100, integer: true, unit: '%', ariaLabel: 'Laboratory follow-up by disease',
            footer: function (items) { var r = lab[items[0].dataIndex]; return r.cases + ' cases, ' + r.tested + ' tested, ' + r.positive + ' positive' + (r.cases < floor ? ' (small number)' : ''); },
            series: [{ label: 'Cases with a finished test (%)', data: lab.map(function (r) { return r.tested_pct; }), color: '#0072b2' }, { label: 'Positive among tested (%)', data: lab.map(function (r) { return r.positive_pct; }), color: '#d55e00' }] });
    });
}());
</script>
@endpush
@endif
