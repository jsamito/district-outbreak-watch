@extends('layouts.app', ['title' => 'Lab worklist'])
@section('content')
    <div class="ow-notice"><i class="fa fa-lock" aria-hidden="true"></i> You see only what is needed to enter a result. Positive results confirm the case automatically.</div>
    <div class="box">
        <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-flask" aria-hidden="true"></i> Waiting for a result <span class="badge badge-muted">{{ $pending->count() }}</span></h2></div>
        <div class="box-body no-padding">
            @if ($pending->isEmpty())
                <div class="ow-empty"><i class="fa fa-check-circle text-green" aria-hidden="true"></i><p>No samples are waiting for a result.</p></div>
            @else
            <table class="table table-hover">
                <caption class="sr-only">Pending laboratory results</caption>
                <thead><tr><th scope="col">Case</th><th scope="col">Disease</th><th scope="col">Age group</th><th scope="col">Test</th><th scope="col">Specimen</th><th scope="col">Collected</th><th scope="col">Enter result</th></tr></thead>
                <tbody>
                @foreach ($pending as $l)
                    <tr>
                        <td><code>{{ \Illuminate\Support\Str::substr($l->case->person->pseudonym, 0, 8) }}</code></td>
                        <td>{{ $l->case->disease->name }}</td>
                        <td>{{ \App\Models\Person::ageGroup($l->case->person->ageOn($l->case->notification_date)) }}</td>
                        <td>{{ $l->test_code }}</td><td>{{ $l->specimen_type }}</td><td>{{ $l->collected_at->format('d M Y') }}</td>
                        <td>
                            <form method="POST" action="{{ route('lab.result', $l) }}" class="form-inline">
                                @csrf
                                <label class="sr-only" for="r-{{ $l->id }}">Result for case {{ \Illuminate\Support\Str::substr($l->case->person->pseudonym, 0, 8) }}</label>
                                <select id="r-{{ $l->id }}" name="result" class="form-control input-sm"><option value="positive">Positive</option><option value="negative">Negative</option><option value="indeterminate">Indeterminate</option></select>
                                <input type="hidden" name="resulted_at" value="{{ now()->toDateString() }}">
                                <button class="btn btn-primary btn-sm" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
@endsection
