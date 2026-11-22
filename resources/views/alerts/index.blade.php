@extends('layouts.app', ['title' => 'Alerts'])

@section('actions')
    <div class="ow-period" role="group" aria-label="Alert status" style="margin:0">
        <a href="{{ route('alerts.index', ['status' => 'active']) }}" class="btn btn-sm {{ $status === 'active' ? 'btn-primary' : 'btn-default' }}">Active <span class="badge badge-muted">{{ $counts['active'] }}</span></a>
        <a href="{{ route('alerts.index', ['status' => 'resolved']) }}" class="btn btn-sm {{ $status === 'resolved' ? 'btn-primary' : 'btn-default' }}">Resolved <span class="badge badge-muted">{{ $counts['resolved'] }}</span></a>
        <a href="{{ route('alerts.index', ['status' => 'all']) }}" class="btn btn-sm {{ $status === 'all' ? 'btn-primary' : 'btn-default' }}">All</a>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-9">
            <div class="box">
                <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-bell-o" aria-hidden="true"></i> {{ ucfirst($status) }} alerts</h2></div>
                <div class="box-body no-padding">
                    @if ($alerts->isEmpty())
                        <div class="ow-empty"><i class="fa fa-check-circle text-green" aria-hidden="true"></i><p>No alerts to show.</p></div>
                    @else
                    <table id="alerts-table" class="table table-hover">
                        <caption class="sr-only">Alerts</caption>
                        <thead><tr><th scope="col">Severity</th><th scope="col">Disease</th><th scope="col">Area</th><th scope="col">Week</th><th scope="col" class="text-right">Cases</th><th scope="col" class="text-right">Expected</th><th scope="col">Status</th><th scope="col" class="noExport"><span class="sr-only">Open</span></th></tr></thead>
                        <tbody>
                        @foreach ($alerts as $a)
                            <tr>
                                <td data-order="{{ ['high' => 1, 'medium' => 2, 'low' => 3][$a->severity] }}"><span class="ow-sig sev-{{ $a->severity }}">{{ ucfirst($a->severity) }}</span></td>
                                <td>{{ $a->disease->name }}</td>
                                <td>{{ $a->orgUnit->name }}</td>
                                <td data-order="{{ $a->last_signal_week->toDateString() }}">{{ $a->last_signal_week->format('d M Y') }}@if ($a->signal_weeks > 1)<small class="text-muted" style="display:block">{{ $a->signal_weeks }} weeks</small>@endif</td>
                                <td class="text-right">{{ number_format($a->observed) }}</td>
                                <td class="text-right">{{ $a->rule->method === 'single_case' ? 'any case' : number_format($a->expected, 1) }}</td>
                                <td><span class="badge badge-{{ ['open' => 'overdue', 'escalated' => 'overdue', 'acknowledged' => 'scheduled', 'resolved' => 'completed'][$a->status] }}">{{ ucfirst($a->status) }}</span>@if ($a->status === 'escalated')<small class="text-muted" style="display:block">level {{ $a->escalation_level }}</small>@endif</td>
                                <td class="noExport text-right"><a class="btn btn-default btn-xs" style="width:auto" href="{{ route('alerts.show', $a) }}">Open<span class="sr-only"> alert for {{ $a->disease->name }} in {{ $a->orgUnit->name }}</span></a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="box">
                <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-envelope-o" aria-hidden="true"></i> Notification preferences</h2></div>
                <form method="POST" action="{{ route('alerts.preferences') }}" class="box-body">
                    @csrf
                    <div class="radio"><label><input type="radio" name="mode" value="auto" @checked($preference === 'auto')> High severity by email at once; the rest in one daily summary</label></div>
                    <div class="radio"><label><input type="radio" name="mode" value="digest" @checked($preference === 'digest')> One daily summary only</label></div>
                    <div class="radio"><label><input type="radio" name="mode" value="off" @checked($preference === 'off')> No email (alerts stay in the list)</label></div>
                    <button class="btn btn-primary btn-sm" type="submit">Save</button>
                    <p class="help-block" style="margin-top:10px">To limit interruptions, you receive at most {{ \App\Services\AlertDispatcher::MAX_INSTANT_PER_HOUR }} immediate emails per hour; the rest join the summary. Unacknowledged alerts escalate to the next level.</p>
                </form>
            </div>
        </div>
    </div>
@endsection



@push('scripts')
<script @nonce>owRegisterDT('alerts-table', { order: [[0, 'asc'], [3, 'desc']], pageLength: 15 });</script>
@endpush
