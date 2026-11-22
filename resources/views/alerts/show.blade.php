@extends('layouts.app', ['title' => $title, 'breadcrumbs' => ['Alerts' => route('alerts.index'), $alert->disease->name.' in '.$alert->orgUnit->name => '#']])
@section('subtitle', $subtitle)

@section('content')
    <section class="ow-strip" aria-label="Alert summary">
        <div><div class="s-label">Severity</div><div class="s-num"><span class="ow-sig sev-{{ $alert->severity }}" style="font-size:16px">{{ ucfirst($alert->severity) }}</span></div><div class="s-hint">{{ $alert->rule->name }}</div></div>
        <div><div class="s-label">Cases this week</div><div class="s-num">{{ number_format($alert->observed) }}</div><div class="s-hint">week starting {{ $alert->last_signal_week->format('d M Y') }}</div></div>
        <div><div class="s-label">Expected</div><div class="s-num">{{ $alert->rule->method === 'single_case' ? 'any case' : number_format($alert->expected, 1) }}</div><div class="s-hint">@unless ($alert->rule->method === 'single_case')threshold {{ number_format($alert->upper_limit, 1) }}@endunless</div></div>
        <div><div class="s-label">Status</div><div class="s-num" style="font-size:18px">{{ ucfirst($alert->status) }}</div><div class="s-hint">first signalled {{ $alert->period_start->format('d M Y') }} · {{ $alert->signal_weeks }} week(s)</div></div>
    </section>

    <div class="row ow-eq">
        <div class="col-md-8">
            <x-chart-card id="chart-alert" :title="$alert->disease->name.' in '.$alert->orgUnit->name" :height="300"
                :summary="'Weekly cases for the last 26 weeks. Diamonds are weeks above the outbreak threshold. The axis starts at zero.'" />
        </div>
        <div class="col-md-4">
            <div class="box">
                <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-check-square-o" aria-hidden="true"></i> Response</h2></div>
                <div class="box-body">
                    @if ($alert->acknowledged_at)
                        <p><i class="fa fa-check-circle text-green" aria-hidden="true"></i> Acknowledged by <strong>{{ $alert->acknowledger?->name ?? 'a user' }}</strong> on {{ $alert->acknowledged_at->format('d M Y H:i') }}.</p>
                        @if ($alert->note)<blockquote style="font-size:13px">{{ $alert->note }}</blockquote>@endif
                    @endif
                    @if ($canAcknowledge)
                        <form method="POST" action="{{ route('alerts.acknowledge', $alert) }}">
                            @csrf
                            <div class="form-group">
                                <label for="note">Note (what you are doing about it)</label>
                                <textarea id="note" name="note" rows="3" maxlength="500" class="form-control">{{ old('note') }}</textarea>
                                @error('note')<span class="help-block text-danger">{{ $message }}</span>@enderror
                            </div>
                            <button class="btn btn-primary" type="submit"><i class="fa fa-check" aria-hidden="true"></i> Acknowledge alert</button>
                        </form>
                    @elseif (! $alert->acknowledged_at)
                        <p class="text-muted">You can view this alert but not acknowledge it.</p>
                    @endif
                    <p style="margin-top:12px"><a href="{{ route('trends', ['area' => $alert->org_unit_id, 'disease' => $alert->disease->code]) }}">Open full trend for this area</a></p>
                </div>
            </div>
            <div class="box">
                <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-history" aria-hidden="true"></i> Timeline</h2></div>
                <ul class="ow-list">
                    <li><i class="fa fa-flag text-muted" aria-hidden="true"></i><span class="ow-list-main">Signalled<small>{{ $alert->triggered_at->format('d M Y H:i') }}</small></span></li>
                    @if ($alert->escalated_at)<li><i class="fa fa-arrow-up text-muted" aria-hidden="true"></i><span class="ow-list-main">Escalated to level {{ $alert->escalation_level }}<small>{{ $alert->escalated_at->format('d M Y H:i') }}</small></span></li>@endif
                    @if ($alert->acknowledged_at)<li><i class="fa fa-check text-muted" aria-hidden="true"></i><span class="ow-list-main">Acknowledged<small>{{ $alert->acknowledged_at->format('d M Y H:i') }}</small></span></li>@endif
                    @if ($alert->resolved_at)<li><i class="fa fa-check-circle text-muted" aria-hidden="true"></i><span class="ow-list-main">Resolved<small>{{ $alert->resolved_at->format('d M Y H:i') }}</small></span></li>@endif
                    @php $sent = $alert->deliveries->where('status', 'sent'); @endphp
                    <li><i class="fa fa-envelope-o text-muted" aria-hidden="true"></i><span class="ow-list-main">{{ $sent->count() }} email(s) sent, {{ $alert->deliveries->where('status', 'queued')->count() }} waiting for the daily summary
                        @if ($seeRecipients)<small>{{ $alert->deliveries->pluck('user.name')->unique()->join(', ') ?: 'No recipients' }}</small>@endif</span></li>
                </ul>
            </div>
        </div>
    </div>
@endsection

@push('plugins')
<script src="{{ asset_ver('vendor/chartjs/chart.umd.js') }}"></script>
<script src="{{ asset_ver('js/charts.js') }}"></script>
@endpush
@push('scripts')
<script @nonce>
owCharts.line('chart-alert', { labels: @js($labels), yTitle: 'Cases per week', floor: @js(config('surveillance.small_number_floor')), ariaLabel: 'Weekly cases with outbreak threshold',
    series: [{ label: 'Weekly cases', data: @js($cases), signals: @js($signals) }, { label: 'Signal threshold', data: @js($upper), color: '#6b7682', dashed: true }] });
</script>
@endpush
