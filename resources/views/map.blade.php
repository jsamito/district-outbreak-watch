@extends('layouts.app', ['title' => 'Map and comparison'])
@section('subtitle', $subtitle)

@section('content')
    @include('partials.filters', ['extra' => '<div class="col-sm-6 col-md-2 form-group"><label for="f-metric">Measure</label><select id="f-metric" name="metric" class="form-control"><option value="incidence"'.($metric === 'incidence' ? ' selected' : '').'>Cases per 100,000</option><option value="cases"'.($metric === 'cases' ? ' selected' : '').'>Number of cases</option></select></div>'])

    <div class="row ow-eq">
        <div class="col-md-7">
            <div class="box">
                <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-map-o" aria-hidden="true"></i> {{ $disease->name }} by district</h2></div>
                <div class="box-body">
                    <div id="district-map" class="ow-map" role="region" aria-label="Map of {{ $disease->name }} by district. The table beside it lists the same values."></div>
                    <p class="ow-caption">Period {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}. Darker districts have a higher {{ $metric === 'incidence' ? 'incidence per 100,000 people' : 'number of cases' }}. Districts outlined in vermillion are above their outbreak threshold in the latest week. Select a district to drill down.</p>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <x-chart-card id="chart-districts" :title="'Highest '.($metric === 'incidence' ? 'incidence' : 'case counts')" icon="fa-bar-chart" :height="420"
                :summary="'Top districts by '.($metric === 'incidence' ? 'cases per 100,000 people' : 'cases').'. Select a bar to open that district. The axis starts at zero.'" />
        </div>
    </div>

    <div class="box">
        <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-table" aria-hidden="true"></i> Districts</h2></div>
        <div class="box-body no-padding">
            <table id="district-table" class="table table-hover table-condensed">
                <caption class="sr-only">Cases, population and incidence by district</caption>
                <thead><tr><th scope="col">District</th><th scope="col" class="text-right">Cases</th><th scope="col" class="text-right">Population</th><th scope="col" class="text-right">Per 100,000</th><th scope="col">Status</th></tr></thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td><a href="{{ route('map', ['area' => $r->id, 'disease' => $disease->code, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'metric' => $metric]) }}">{{ $r->name }}</a></td>
                        <td class="text-right">{{ number_format($r->cases) }}</td>
                        <td class="text-right">{{ $r->population ? number_format($r->population) : 'n/a' }}</td>
                        <td class="text-right" data-order="{{ $r->incidence ?? 0 }}">{{ $r->incidence !== null ? number_format($r->incidence, 1) : 'n/a' }}</td>
                        <td>@if (isset($flagRows[$r->id]))<span class="ow-sig sev-{{ $flagRows[$r->id]->severity }}">Above threshold</span>@endif @if ($r->cases > 0 && $r->cases < config('surveillance.small_number_floor'))<span class="ow-flag">Small number</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($facilities->isNotEmpty())
    <div class="box">
        <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-hospital-o" aria-hidden="true"></i> Reporting facilities in {{ $mapRoot->name }}</h2></div>
        <div class="box-body no-padding">
            <table id="facility-table" class="table table-hover table-condensed">
                <caption class="sr-only">Cases and reporting completeness by facility</caption>
                <thead><tr><th scope="col">Facility</th><th scope="col" class="text-right">Cases</th><th scope="col" class="text-right">Reports received (%)</th></tr></thead>
                <tbody>
                @foreach ($facilities as $f)
                    <tr><td><a href="{{ route('trends', ['area' => $mapRoot->id, 'facility' => $f->id, 'disease' => $disease->code]) }}">{{ $f->name }}</a></td><td class="text-right">{{ number_format($f->cases) }}</td><td class="text-right">{{ $f->reports !== null ? number_format($f->reports, 0) : 'n/a' }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
@endsection

@push('plugin-styles')
<link rel="stylesheet" href="{{ asset_ver('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset_ver('vendor/datatables/datatables.min.css') }}">
@endpush
@push('plugins')
<script src="{{ asset_ver('vendor/chartjs/chart.umd.js') }}"></script>
<script src="{{ asset_ver('js/charts.js') }}"></script>
<script src="{{ asset_ver('vendor/leaflet/leaflet.js') }}"></script>
<script src="{{ asset_ver('js/map.js') }}"></script>
<script src="{{ asset_ver('vendor/datatables/datatables.min.js') }}"></script>
@endpush
@push('scripts')
<script @nonce>
(function () {
    var base = @js(route('map', ['disease' => $disease->code, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'metric' => $metric]).'&area=');
    owMap('district-map', { geojsonUrl: @js(route('data.districts', ['area' => $area->type === 'facility' ? $area->parent_id : $area->id])), values: @js($values), flags: @js($flags),
        legendTitle: @js($metric === 'incidence' ? 'Cases per 100,000' : 'Cases'), linkBase: base });
    var top = @js($sorted->take(15)->values());
    var metric = @js($metric);
    owCharts.lazy('chart-districts', function () { owCharts.bar('chart-districts', { horizontal: true, labels: top.map(function (r) { return r.name; }), yTitle: metric === 'incidence' ? 'Cases per 100,000 people' : 'Cases', integer: metric !== 'incidence',
        floor: @js(config('surveillance.small_number_floor')), ariaLabel: 'Districts ranked', onClick: function (i) { window.location = base + top[i].id; },
        series: [{ label: metric === 'incidence' ? 'Per 100,000' : 'Cases', data: top.map(function (r) { return Math.round((metric === 'incidence' ? r.incidence : r.cases) * 10) / 10; }) }] }); });
    owRegisterDT('district-table', { order: [[3, 'desc']], pageLength: 15 });
    owRegisterDT('facility-table', { order: [[1, 'desc']], pageLength: 10 });
}());
</script>
@endpush
