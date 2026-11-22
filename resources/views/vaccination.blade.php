@extends('layouts.app', ['title' => 'Vaccination coverage'])
@section('subtitle', $subtitle)

@section('content')
    @php
        $extra = '<div class="col-sm-6 col-md-3 form-group"><label for="f-antigen">Vaccine</label><select id="f-antigen" name="antigen" class="form-control" onchange="this.form.submit()">';
        foreach ($antigens as $a) { $extra .= '<option value="'.e($a->code).'"'.($a->id === $antigen->id ? ' selected' : '').'>'.e($a->name).'</option>'; }
        $extra .= '</select></div><div class="col-sm-6 col-md-2 form-group"><label for="f-months">Months</label><select id="f-months" name="months" class="form-control">';
        foreach ([6, 12, 24, 36] as $m) { $extra .= '<option value="'.$m.'"'.($m === $months ? ' selected' : '').'>'.$m.'</option>'; }
        $extra .= '</select></div>';
    @endphp
    @include('partials.filters', ['extra' => $extra, 'showDates' => false])

    <section class="ow-strip" aria-label="Coverage summary">
        @foreach ($antigens as $a)
            @php $c = $overall[$a->id]['coverage'] ?? null; @endphp
            <div>
                <div class="s-label"><i class="fa fa-medkit" aria-hidden="true"></i> {{ $a->code }}</div>
                <div class="s-num">{{ $c !== null ? number_format($c, 0).'%' : 'n/a' }}</div>
                <div class="s-hint">target {{ number_format($a->target_percent, 0) }}% @if ($c !== null && $c > 100)· <span class="ow-flag">above 100%: check data</span>@endif</div>
            </div>
        @endforeach
    </section>

    <div class="row ow-eq">
        <div class="col-md-7">
            <x-chart-card id="chart-coverage" :title="$antigen->name.': coverage by area'" icon="fa-bar-chart" :height="440"
                :summary="'Doses given divided by the expected number of children under one year over the last '.$months.' months. The vermillion line is the national target of '.number_format($antigen->target_percent, 0).'%'.($who ? '; the dashed line is the WHO/UNICEF national estimate for '.$who->year.' ('.number_format($who->coverage_percent, 0).'%)' : '').'. The axis starts at zero.'">
                <x-slot:table>
                    <table class="table table-condensed"><thead><tr><th>Area</th><th class="text-right">Doses</th><th class="text-right">Target population</th><th class="text-right">Coverage</th></tr></thead><tbody>
                        @foreach ($byChild as $c)<tr><td>{{ $c->name }}</td><td class="text-right">{{ number_format($c->doses) }}</td><td class="text-right">{{ number_format($c->target) }}</td><td class="text-right">{{ $c->coverage !== null ? number_format($c->coverage, 1).'%' : 'n/a' }}</td></tr>@endforeach
                    </tbody></table>
                </x-slot:table>
            </x-chart-card>
        </div>
        <div class="col-md-5">
            <x-chart-card id="chart-covtrend" title="Monthly coverage, last 24 months" icon="fa-line-chart" :height="440"
                summary="Monthly doses divided by the monthly share of the under-one population. Dips in December and January reflect fewer clinic days.">
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
    var rows = @js($byChild->values());
    var target = @js((float) $antigen->target_percent);
    var who = @js($who ? (float) $who->coverage_percent : null);
    var series = [{ label: 'Coverage', data: rows.map(function (r) { return r.coverage === null ? null : Math.round(r.coverage * 10) / 10; }),
        colors: rows.map(function (r) { return r.coverage !== null && r.coverage > 100 ? '#cc79a7' : (r.coverage !== null && r.coverage < target ? '#e69f00' : '#0072b2'); }) }];
    owCharts.bar('chart-coverage', { horizontal: true, labels: rows.map(function (r) { return r.name; }), yTitle: 'Coverage (%)', yMax: 120, integer: false, unit: '%', series: series, legend: false,
        ariaLabel: @js('Vaccination coverage by area for '.$antigen->name) });
    var t = @js($trend);
    var months = Object.keys(t);
    owCharts.line('chart-covtrend', { labels: months, yTitle: 'Coverage (%)', yMax: 120, integer: false, unit: '%', ariaLabel: 'Monthly coverage',
        series: [{ label: 'Coverage', data: months.map(function (m) { return t[m].value === null ? null : Math.round(t[m].value * 10) / 10; }) },
                 { label: 'Target', data: months.map(function () { return target; }), color: '#d55e00', dashed: true }].concat(who !== null ? [{ label: 'WHO national estimate', data: months.map(function () { return who; }), color: '#6b7682', dashed: true }] : []) });
}());
</script>
@endpush
