@extends('layouts.app', ['title' => 'Public attention'])
@section('subtitle', $subtitle)

@section('content')
    @include('partials.filters')
    <div class="ow-notice"><i class="fa fa-info-circle" aria-hidden="true"></i> Page views are for the English edition of Wikipedia worldwide, not for {{ config('surveillance.country.name') }} alone. They show general public attention, not local demand.</div>

    <x-chart-card id="chart-attention" :title="$disease->name.': public attention and reported cases'" icon="fa-globe" :height="340"
        :summary="'Weekly Wikipedia page views for the '.$disease->name.' article (left axis) with reported cases in '.$area->name.' (right axis). Both axes start at zero. A surge in attention without a rise in cases can signal media coverage or misinformation.'">
        <x-slot:table>
            <table class="table table-condensed"><thead><tr><th>Week starting</th><th class="text-right">Page views</th><th class="text-right">Attention index</th><th class="text-right">Cases</th></tr></thead><tbody>
                @foreach (array_reverse(array_keys($labels)) as $i)<tr><td>{{ $labels[$i] }}</td><td class="text-right">{{ $views[$i] !== null ? number_format($views[$i]) : 'n/a' }}</td><td class="text-right">{{ $index[$i] ?? 'n/a' }}</td><td class="text-right">{{ $cases[$i] !== null ? number_format($cases[$i]) : 'n/a' }}</td></tr>@endforeach
            </tbody></table>
        </x-slot:table>
    </x-chart-card>
@endsection

@push('plugins')
<script src="{{ asset_ver('vendor/chartjs/chart.umd.js') }}"></script>
<script src="{{ asset_ver('js/charts.js') }}"></script>
@endpush
@push('scripts')
<script @nonce>
(function () {
    var el = document.getElementById('chart-attention');
    el.setAttribute('role', 'img'); el.setAttribute('aria-label', 'Page views and reported cases by week');
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    new Chart(el, { type: 'line', data: { labels: @js($labels), datasets: [
        { label: 'Wikipedia page views', data: @js($views), borderColor: owColors[0], backgroundColor: owColors[0], yAxisID: 'y', pointStyle: 'circle', tension: 0.15 },
        { label: 'Reported cases', data: @js($cases), borderColor: owColors[3], backgroundColor: owColors[3], yAxisID: 'y1', pointStyle: 'triangle', borderDash: [6, 3], tension: 0.15 } ] },
        options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true } } },
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Page views per week' } }, y1: { beginAtZero: true, position: 'right', grid: { display: false }, title: { display: true, text: 'Reported cases per week' } },
                x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } } } } });
}());
</script>
@endpush
