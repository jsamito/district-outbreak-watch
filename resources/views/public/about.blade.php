@extends('layouts.public')
@section('title', 'About the data')
@section('description', 'Where the data come from, their licences and what you may do with them.')
@push('head')
<script type="application/ld+json" @nonce>{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Dataset', 'name' => 'District Outbreak Watch data sources', 'description' => 'Open geography and population data for Uganda with sample weekly surveillance data.', 'url' => route('about'),
    'license' => 'https://creativecommons.org/licenses/by/3.0/igo/', 'creator' => ['@type' => 'Organization', 'name' => config('app.name')],
    'distribution' => $sources->filter(fn ($s) => $s->url)->map(fn ($s) => ['@type' => 'DataDownload', 'contentUrl' => $s->url, 'name' => $s->name])->values()], JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
@section('content')
    <h1 id="data">About the data</h1>
    <p class="lead">Every dataset used here is listed with its source, licence and the date it was obtained. No real patient information is held, and no identifiable data are used.</p>
    <table class="table table-bordered">
        <caption class="sr-only">Data sources</caption>
        <thead><tr><th scope="col">Dataset</th><th scope="col">Licence and terms</th><th scope="col">Accessed</th><th scope="col">Type</th></tr></thead>
        <tbody>
        @foreach ($sources as $s)
            <tr>
                <th scope="row">@if ($s->url)<a href="{{ $s->url }}" rel="noopener">{{ $s->name }}</a>@else{{ $s->name }}@endif<br><small class="text-muted">{{ $s->citation }}</small></th>
                <td>{{ $s->licence }}@if ($s->terms_note)<br><small class="text-muted">{{ $s->terms_note }}</small>@endif</td>
                <td>{{ $s->accessed_at?->format('d M Y') }}</td>
                <td>{{ $s->synthetic ? 'Generated sample data' : 'Open data' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <h2>Known limits</h2>
    <ul>
        <li>Facility types come from OpenStreetMap-derived names and are not Uganda's official health-centre levels.</li>
        <li>Population is a single projected year per district; other years are extrapolated and marked as projected.</li>
        <li>Wikipedia page views are worldwide for the English edition, not specific to Uganda.</li>
        <li>No open weekly facility-level disease series exists for Uganda; the sample series are generated from real populations and clearly labelled. Replace them with live reports when available.</li>
    </ul>
    @if ($batches->isNotEmpty())
    <h2>Recent data loads</h2>
    <table class="table table-condensed"><thead><tr><th scope="col">Loaded</th><th scope="col">Process</th><th scope="col">Source</th><th scope="col" class="text-right">Rows</th></tr></thead><tbody>
        @foreach ($batches as $b)<tr><td>{{ $b->finished_at?->format('d M Y H:i') }}</td><td>{{ $b->command }}</td><td>{{ $b->source?->name }}</td><td class="text-right">{{ number_format($b->rows_in) }}</td></tr>@endforeach
    </tbody></table>
    @endif
@endsection
