@extends('layouts.public')
@section('title', 'National disease situation')
@section('description', 'National weekly counts of notifiable diseases in Uganda from sentinel facilities.')
@section('content')
    <h1>National situation</h1>
    @if (! $week)
        <p>No data has been loaded yet.</p>
    @else
        <p class="lead">Week starting {{ $week->format('d F Y') }} (last full week).</p>
        @if (config('surveillance.synthetic_data'))<div class="alert alert-warning" role="note"><strong>Data notice:</strong> the figures below are generated sample data and do not describe the real disease situation.</div>@endif
        <table class="table table-bordered">
            <caption class="sr-only">National cases by disease for the last full week</caption>
            <thead><tr><th scope="col">Disease</th><th scope="col" class="text-right">Cases this week</th><th scope="col" class="text-right">Week before</th><th scope="col" class="text-right">Change</th></tr></thead>
            <tbody>
            @foreach ($rows as $r)
                <tr><th scope="row">{{ $r->disease->name }} <small class="text-muted">ICD-10 {{ $r->disease->icd10_code }}</small></th>
                    <td class="text-right">{{ number_format($r->cases) }}@if ($r->cases > 0 && $r->cases < config('surveillance.small_number_floor')) <small>(small number)</small>@endif</td>
                    <td class="text-right">{{ number_format($r->prev) }}</td>
                    <td class="text-right">@if ($r->delta === null) n/a @else {{ $r->delta > 0 ? '+' : '' }}{{ $r->delta }}% @endif</td></tr>
            @endforeach
            </tbody>
        </table>
        <p class="text-muted">Counts come from sentinel health facilities and are not a complete count of cases. Percentage changes are not shown when the previous week was zero. Figures below {{ config('surveillance.small_number_floor') }} are small numbers and should be interpreted with caution.</p>
        <p><a href="{{ route('share') }}" class="btn btn-default"><i class="fa fa-share-alt" aria-hidden="true"></i> Share this summary</a></p>
    @endif
@endsection
