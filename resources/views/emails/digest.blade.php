<!DOCTYPE html>
<html lang="en"><body style="font-family:Arial,Helvetica,sans-serif;color:#1f2933;line-height:1.5;max-width:600px">
<h2 style="margin:0 0 8px">Daily alert summary</h2>
<p>Hello {{ $user->name }}, these alerts in your area are open:</p>
<table cellpadding="6" style="border-collapse:collapse;border:1px solid #e4e7eb;width:100%">
    <tr style="background:#f5f6f8"><th align="left">Disease</th><th align="left">Area</th><th align="right">Cases</th><th align="right">Expected</th><th align="left">Severity</th></tr>
    @foreach ($alerts as $a)
    <tr><td><a href="{{ route('alerts.show', $a) }}">{{ $a->disease->name }}</a></td><td>{{ $a->orgUnit->name }}</td><td align="right">{{ number_format($a->observed) }}</td><td align="right">{{ number_format($a->expected, 1) }}</td><td>{{ ucfirst($a->severity) }}</td></tr>
    @endforeach
</table>
<p style="color:#5b6773;font-size:12px">Aggregated counts only. To change how often you are emailed, open Alerts &gt; Notification preferences.</p>
</body></html>
