@extends('layouts.app', ['title' => 'Trends'])
@section('subtitle', $subtitle)

@section('content')
    @include('partials.filters')

    <x-chart-card id="chart-trend" :title="$disease->name.': weekly cases'" :height="340"
        :summary="'Weekly '.$disease->name.' cases in '.$area->name.' from '.$from->format('d M Y').' to '.$to->format('d M Y').' ('.number_format($total).' cases in total). The dotted line is the same weeks one year earlier; the dashed line is a four-week moving average; diamonds are weeks above the outbreak threshold. The axis starts at zero.'">
        <x-slot:table>
            @include('partials.trend-table')
        </x-slot:table>
    </x-chart-card>

    <div class="box">
        <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-table" aria-hidden="true"></i> Weekly values with denominators</h2></div>
        <div class="box-body no-padding">
            @include('partials.trend-table', ['tableId' => 'trend-table', 'datatable' => true])
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
    owCharts.line('chart-trend', { labels: @js($labels), yTitle: 'Cases per week', floor: @js(config('surveillance.small_number_floor')), ariaLabel: @js('Weekly cases of '.$disease->name.' in '.$area->name),
        series: [{ label: 'Weekly cases', data: @js($cases), signals: @js($signals) },
                 { label: 'Four-week average', data: @js($ma), color: '#009e73', dashed: true },
                 { label: 'Same weeks last year', data: @js($prevYear), color: '#6b7682' }] });
    owRegisterDT('trend-table', { order: [[0, 'desc']], pageLength: 12 });
}());
</script>
@endpush
