@extends('layouts.app', ['title' => 'Alert rules'])
@section('actions')<a href="{{ route('alert-rules.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus" aria-hidden="true"></i> New rule</a>@endsection

@section('content')
    <div class="ow-notice"><i class="fa fa-info-circle" aria-hidden="true"></i> Rules decide when a signal becomes an alert and who is told. To keep alerts useful: set a minimum count, keep one alert per area and disease while the signal lasts (cooldown), send only high severity at once, and escalate what nobody acknowledges.</div>
    <div class="box">
        <div class="box-body no-padding">
            <table class="table table-hover">
                <caption class="sr-only">Alert rules</caption>
                <thead><tr><th scope="col">Rule</th><th scope="col">Applies to</th><th scope="col">Method</th><th scope="col" class="text-right">Min. cases</th><th scope="col" class="text-right">Cooldown</th><th scope="col">Escalates after</th><th scope="col">Notifies</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Edit</span></th></tr></thead>
                <tbody>
                @foreach ($rules as $r)
                    <tr>
                        <td><strong>{{ $r->name }}</strong></td>
                        <td>{{ $r->disease?->name ?? 'Matching diseases' }} · {{ $r->orgUnit?->name ?? 'all areas' }}</td>
                        <td>{{ \App\Models\AlertRule::METHODS[$r->method] ?? $r->method }}</td>
                        <td class="text-right">{{ $r->min_count }}</td>
                        <td class="text-right">{{ $r->cooldown_hours }} h</td>
                        <td>{{ $r->escalate_after_minutes ? $r->escalate_after_minutes.' min' : 'never' }}</td>
                        <td>{{ collect($r->audience_roles)->map(fn ($x) => str_replace('_', ' ', $x))->join(', ') }}</td>
                        <td><span class="badge badge-{{ $r->enabled ? 'completed' : 'muted' }}">{{ $r->enabled ? 'On' : 'Off' }}</span></td>
                        <td class="text-right"><a class="btn btn-default btn-xs" style="width:auto" href="{{ route('alert-rules.edit', $r) }}">Edit<span class="sr-only"> {{ $r->name }}</span></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
