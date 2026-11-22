<!DOCTYPE html>
<html lang="en"><body style="font-family:Arial,Helvetica,sans-serif;color:#1f2933;line-height:1.5;max-width:560px">
<h2 style="margin:0 0 8px">{{ $escalated ? 'Escalated: ' : '' }}{{ ucfirst($alert->severity) }} alert</h2>
<p><strong>{{ $alert->disease->name }}</strong> in <strong>{{ $alert->orgUnit->name }}</strong>, week starting {{ $alert->last_signal_week->format('d M Y') }}.</p>
<table cellpadding="6" style="border-collapse:collapse;border:1px solid #e4e7eb">
    <tr><td>Cases reported this week</td><td><strong>{{ number_format($alert->observed) }}</strong></td></tr>
    @if ($alert->rule->method !== 'single_case')
    <tr><td>Expected from the previous weeks</td><td>{{ number_format($alert->expected, 1) }}</td></tr>
    <tr><td>Outbreak threshold</td><td>{{ number_format($alert->upper_limit, 1) }}</td></tr>
    @else
    <tr><td>Reason</td><td>Immediately notifiable disease: any case triggers this alert.</td></tr>
    @endif
</table>
<p>{{ $escalated ? 'This alert was not acknowledged in time and has been escalated to you.' : 'Please review the situation and acknowledge the alert.' }}</p>
<p><a href="{{ route('alerts.show', $alert) }}" style="background:#2f6fba;color:#fff;padding:10px 16px;text-decoration:none;border-radius:4px;display:inline-block">Open the alert</a></p>
<p style="color:#5b6773;font-size:12px">This message contains aggregated counts only. You receive it because of your role and area of responsibility; change how often you are emailed under Alerts &gt; Notification preferences.</p>
</body></html>
