@extends('layouts.app', ['title' => 'Forecasts'])
@section('subtitle', $subtitle)

@section('content')
    @include('partials.filters', ['showDates' => false])

    @if (! $result)
        <div class="ow-notice">There is not enough history to project this series (at least {{ 26 + 8 }} weeks are needed).</div>
    @else
        <div class="ow-notice"><i class="fa fa-info-circle" aria-hidden="true"></i> <strong>Projection, not a prediction of certainty.</strong> The next four weeks are extrapolated from past counts only; they cannot anticipate a new outbreak, a change in reporting or a response. Shaded bands show where 80% and 95% of outcomes would be expected to fall if the recent pattern continued.</div>

        <section class="ow-strip" aria-label="Projection summary">
            <div><div class="s-label">Next week</div><div class="s-num">{{ number_format($result['points'][0]['point']) }}</div><div class="s-hint">80% range {{ number_format($result['points'][0]['lower80']) }} to {{ number_format($result['points'][0]['upper80']) }}</div></div>
            <div><div class="s-label">In four weeks</div><div class="s-num">{{ number_format($result['points'][3]['point']) }}</div><div class="s-hint">80% range {{ number_format($result['points'][3]['lower80']) }} to {{ number_format($result['points'][3]['upper80']) }}</div></div>
            <div><div class="s-label">Method</div><div class="s-num" style="font-size:18px">{{ $result['label'] }}</div><div class="s-hint">chosen by backtest accuracy</div></div>
            <div><div class="s-label">Backtest error</div><div class="s-num">{{ number_format($result['backtest']['mae'], 1) }}</div><div class="s-hint">cases per week, vs {{ number_format($result['backtest']['naive_mae'], 1) }} for "same as last week"</div></div>
            <div><div class="s-label">80% interval coverage</div><div class="s-num">{{ number_format($result['backtest']['coverage80'] * 100, 0) }}%</div><div class="s-hint">of past weeks fell inside (target 80%)</div></div>
        </section>

        @unless ($result['useful'])
            <div class="ow-notice" style="border-left-color:#e69f00"><strong>Low confidence.</strong> This method is not clearly more accurate than simply repeating last week's count over the past {{ $result['backtest']['origins'] }} backtests (improvement {{ number_format($result['backtest']['skill'] * 100, 0) }}%). Treat the projection as a rough guide.</div>
        @endunless

        <x-chart-card id="chart-forecast" :title="$disease->name.': projection for the next four weeks'" icon="fa-area-chart" :height="360"
            :summary="'Reported weekly cases for the last 26 weeks and projected cases for the next four weeks in '.$area->name.'. Method: '.$result['label'].'. The axis starts at zero.'">
            <x-slot:table>
                <table class="table table-condensed"><thead><tr><th>Week starting</th><th class="text-right">Projected</th><th class="text-right">80% range</th><th class="text-right">95% range</th></tr></thead><tbody>
                    @foreach ($result['points'] as $i => $p)<tr><td>{{ $futureWeeks[$i] }}</td><td class="text-right">{{ number_format($p['point']) }}</td><td class="text-right">{{ number_format($p['lower80']) }} to {{ number_format($p['upper80']) }}</td><td class="text-right">{{ number_format($p['lower95']) }} to {{ number_format($p['upper95']) }}</td></tr>@endforeach
                </tbody></table>
            </x-slot:table>
        </x-chart-card>

        <div class="box"><div class="box-header with-border"><h3 class="box-title">How accurate have these projections been?</h3></div>
            <div class="box-body">
                <p>Each model was tested on {{ $result['backtest']['origins'] }} past weeks: trained only on data before that week, then compared with what really happened one to four weeks later. Average absolute error per week:</p>
                <ul>@foreach ($result['backtest']['by_method'] as $m => $mae)<li>{{ \App\Services\Forecaster::METHODS[$m] }}: {{ number_format($mae, 1) }}@if ($m === 'naive') (baseline)@endif</li>@endforeach</ul>
            </div>
        </div>
    @endif
@endsection

@if ($result)
@push('plugins')
<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
<script src="{{ asset('js/charts.js') }}"></script>
@endpush
@push('scripts')
<script @nonce>
(function () {
    var p = @js($result['points']);
    owCharts.forecast('chart-forecast', { historyLabels: @js($historyLabels), history: @js($history), futureLabels: @js($futureWeeks),
        point: p.map(function (x) { return x.point; }), lower80: p.map(function (x) { return x.lower80; }), upper80: p.map(function (x) { return x.upper80; }),
        lower95: p.map(function (x) { return x.lower95; }), upper95: p.map(function (x) { return x.upper95; }), ariaLabel: 'Reported weekly cases and four-week projection with intervals' });
}());
</script>
@endpush
@endif
