@extends('layouts.app', ['title' => 'Quality control chart'])
@section('subtitle', $subtitle)

@section('content')
    @include('partials.filters')

    @if ($noPopulation)
        <div class="ow-notice">A control chart needs a population. Choose an area above facility level.</div>
    @endif

    <x-chart-card id="chart-u" :title="$disease->name.': weekly rate with control limits'" icon="fa-bar-chart" :height="360"
        :summary="'u-chart of weekly cases per 100,000 people. Centre line '.number_format($centre, 2).' per 100,000 from '.$baselineWeeks.' baseline weeks. '.$rule.' The axis starts at zero.'">
        <x-slot:table>
            <table class="table table-condensed"><thead><tr><th>Week starting</th><th class="text-right">Cases</th><th class="text-right">Rate per 100,000</th><th class="text-right">Upper limit</th><th>Flag</th></tr></thead><tbody>
                @foreach ($points as $p)<tr><td>{{ $p['week'] }}</td><td class="text-right">{{ number_format($p['cases']) }}</td><td class="text-right">{{ $p['rate'] !== null ? number_format($p['rate'], 2) : 'n/a' }}</td><td class="text-right">{{ $p['ucl'] !== null ? number_format($p['ucl'], 2) : 'n/a' }}</td><td>{{ $p['above_ucl'] ? 'Above upper limit' : ($p['shift'] ? 'Eight weeks above centre' : '') }}</td></tr>@endforeach
            </tbody></table>
        </x-slot:table>
    </x-chart-card>

    <div class="box">
        <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-flag-o" aria-hidden="true"></i> Flagged weeks <span class="badge badge-muted">{{ $flagged->count() }}</span></h2></div>
        <div class="box-body">
            <p class="text-muted">The limits are fixed from the baseline weeks and exclude the most recent 12 weeks, so a growing outbreak cannot widen its own limits.</p>
            @if ($flagged->isEmpty())
                <div class="ow-empty"><i class="fa fa-check-circle text-green" aria-hidden="true"></i><p>No week in this period is outside the control limits.</p></div>
            @else
                <ul class="ow-list">
                    @foreach ($flagged as $p)
                        <li><span class="ow-list-main">Week starting {{ $p['week'] }}<small>{{ number_format($p['cases']) }} cases, {{ number_format($p['rate'], 2) }} per 100,000</small></span>
                            <span class="ow-sig sev-{{ $p['above_ucl'] ? 'high' : 'medium' }}">{{ $p['above_ucl'] ? 'Above upper limit' : 'Sustained shift' }}</span></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection

@push('plugins')
<script src="{{ asset_ver('vendor/chartjs/chart.umd.js') }}"></script>
<script src="{{ asset_ver('js/charts.js') }}"></script>
@endpush
@push('scripts')
<script @nonce>
(function () {
    var p = @js($points);
    owCharts.uchart('chart-u', {
        labels: p.map(function (x) { return x.week; }), rate: p.map(function (x) { return x.rate; }), ucl: p.map(function (x) { return x.ucl; }), lcl: p.map(function (x) { return x.lcl; }),
        centre: p.map(function () { return @js(round($centre, 3)); }), flags: p.map(function (x) { return x.above_ucl || x.shift; }),
        notes: p.map(function (x) { return x.above_ucl ? 'Above upper control limit' : (x.shift ? 'Eight weeks above the centre line' : ''); }),
        ariaLabel: @js('u-chart of weekly '.$disease->name.' rate per 100,000 in '.$area->name)
    });
}());
</script>
@endpush
