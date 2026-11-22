@extends('layouts.app', ['title' => 'Reporting completeness'])
@section('subtitle', $subtitle)

@section('actions')
    <form method="GET" action="{{ route('reporting') }}" class="form-inline" aria-label="Choose area">
        <label for="rp-area" class="sr-only">Area</label>
        <select id="rp-area" name="area" class="form-control input-sm ow-select2" data-autosubmit="1">
            @foreach ($areaChoices as $o)<option value="{{ $o->id }}" @selected($o->id == $area->id)>{{ $o->name }}@if ($o->type === 'region') region @endif</option>@endforeach
        </select>
    </form>
@endsection

@section('content')
    <section class="ow-strip" aria-label="This week">
        <div><div class="s-label"><i class="fa fa-inbox" aria-hidden="true"></i> Received</div><div class="s-num">{{ number_format($timely->received ?? 0) }} / {{ number_format($timely->expected ?? 0) }}</div><div class="s-hint">reports for the week of {{ $latest->format('d M Y') }}</div></div>
        <div><div class="s-label"><i class="fa fa-clock-o" aria-hidden="true"></i> On time</div><div class="s-num">{{ ($timely->expected ?? 0) > 0 ? number_format(100 * ($timely->on_time ?? 0) / $timely->expected, 0).'%' : 'n/a' }}</div><div class="s-hint">received by the Wednesday after the week</div></div>
        <div><div class="s-label"><i class="fa fa-check-square-o" aria-hidden="true"></i> Completeness</div><div class="s-num">{{ ($timely->expected ?? 0) > 0 ? number_format(100 * ($timely->received ?? 0) / $timely->expected, 0).'%' : 'n/a' }}</div><div class="s-hint">of expected reports</div></div>
    </section>

    <x-chart-card id="chart-reporting" title="Completeness, last 26 weeks" icon="fa-line-chart" :height="240"
        summary="Share of expected weekly reports received in the area. Counts in other pages are only as reliable as this reporting."/>

    <div class="box">
        <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-th" aria-hidden="true"></i> {{ $area->type === 'district' ? 'Facilities' : 'Areas' }} by week (lowest completeness first)</h3></div>
        <div class="box-body" style="overflow-x:auto">
            @if ($children->isEmpty())
                <div class="ow-empty"><p>No reporting units below this area.</p></div>
            @else
            <table class="ow-heat" style="min-width:720px">
                <caption class="sr-only">Percent of expected reports received, by week</caption>
                <thead><tr><th scope="col"></th>@foreach ($weeks as $w)<th scope="col">{{ \Carbon\Carbon::parse($w)->format('d M') }}</th>@endforeach<th scope="col">Average</th></tr></thead>
                <tbody>
                @foreach ($children as $c)
                    <tr><th scope="row">{{ $c->name }}</th>
                    @foreach ($weeks as $w)
                        @php $v = $matrix[$c->id][$w] ?? null; $lvl = $v === null ? 0 : ($v < 50 ? 1 : ($v < 75 ? 2 : ($v < 90 ? 3 : 4))); @endphp
                        <td class="lvl-{{ $lvl }}" title="{{ $v === null ? 'No data' : number_format($v, 0).'%' }}">{{ $v === null ? 'n/a' : number_format($v, 0) }}</td>
                    @endforeach
                    <td>{{ $c->avg !== null ? number_format($c->avg, 0).'%' : 'n/a' }}</td></tr>
                @endforeach
                </tbody>
            </table>
            <div class="ow-legend"><i style="background:#f2c6a9"></i> under 50% <i style="background:#f6e0a7"></i> 50-74% <i style="background:#bfe0cf"></i> 75-89% <i style="background:#6fbf9a"></i> 90% or more <i style="background:#f3f5f7;border:1px solid #cfd8df"></i> no data. Values are percentages; the number is shown in every cell.</div>
            @endif
        </div>
    </div>
@endsection

@push('plugins')
<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
<script src="{{ asset('js/charts.js') }}"></script>
@endpush
@push('scripts')
<script @nonce>
owCharts.line('chart-reporting', { labels: @js(array_keys($trend)), yTitle: 'Reports received (%)', yMax: 100, integer: false, unit: '%', ariaLabel: 'Reporting completeness by week',
    series: [{ label: 'Completeness', data: @js(array_map(fn ($v) => round($v, 1), array_values($trend))) }] });
</script>
@endpush
