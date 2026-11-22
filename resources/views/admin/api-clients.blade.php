@extends('layouts.app', ['title' => 'API clients'])
@section('subtitle', $subtitle)

@section('content')
    @if (session('new_token'))
        <div class="alert alert-warning" role="alert"><strong>Token for {{ session('new_token_name') }}:</strong> <code>{{ session('new_token') }}</code><br>Copy it now and give it to the facility system. It is not shown again.</div>
    @endif

    <form method="POST" action="{{ route('admin.api-clients.store') }}" class="ow-filters box" aria-label="Register a facility system">
        @csrf
        <div class="box-body"><div class="row">
            <div class="col-sm-6 col-md-4 form-group @error('name') has-error @enderror"><label for="ac-name">System name</label><input id="ac-name" name="name" class="form-control" maxlength="120" required value="{{ old('name') }}" placeholder="e.g. Kasese Hospital HMIS"></div>
            <div class="col-sm-6 col-md-5 form-group"><label for="ac-area">May report for</label>
                <select id="ac-area" name="org_unit_id" class="form-control ow-select2" required>@foreach ($areas as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->type }})</option>@endforeach</select></div>
            <div class="ow-filter-actions"><button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-plus" aria-hidden="true"></i> Register and create token</button></div>
        </div></div>
    </form>

    <div class="box">
        <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-plug" aria-hidden="true"></i> Registered systems</h2></div>
        <div class="box-body no-padding">
            <table class="table table-hover"><caption class="sr-only">API clients</caption>
                <thead><tr><th scope="col">System</th><th scope="col">May report for</th><th scope="col">Last seen</th><th scope="col" class="text-right">Accepted</th><th scope="col" class="text-right">Rejected</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead>
                <tbody>
                @forelse ($clients as $c)
                    @php $s = $stats[$c->id] ?? null; @endphp
                    <tr><td>{{ $c->name }}</td><td>{{ $c->orgUnit->name }}</td><td>{{ $c->last_seen_at?->diffForHumans() ?? 'Never' }}</td><td class="text-right">{{ number_format($s->applied ?? 0) }}</td><td class="text-right">{{ number_format($s->rejected ?? 0) }}</td>
                        <td>{{ $c->active ? 'Active' : 'Disabled' }}</td>
                        <td class="text-right"><form method="POST" action="{{ route('admin.api-clients.toggle', $c) }}">@csrf<button class="btn btn-default btn-xs" style="width:auto" type="submit">{{ $c->active ? 'Disable' : 'Enable' }}<span class="sr-only"> {{ $c->name }}</span></button></form></td></tr>
                @empty
                    <tr><td colspan="7"><div class="ow-empty"><p>No facility systems are registered yet.</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="box-footer"><small class="text-muted">Systems send a FHIR Bundle or a simple JSON notification to <code>POST /api/v1/notifications</code> with the header <code>Authorization: Bearer &lt;token&gt;</code>. Each system can only report for its own area, and repeating a notification with the same key does not create a second case.</small></div>
    </div>
@endsection
