<?php

namespace App\Services;

use App\Mail\AlertDigestMail;
use App\Mail\AlertMail;
use App\Models\Alert;
use App\Models\AlertDelivery;
use App\Models\AlertPreference;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Decides who is told, how and when, to keep alerts useful:
 *  - recipients are only users whose role matches the rule and whose area covers the alert;
 *  - high-severity alerts go out at once, the rest wait for one daily digest;
 *  - each person receives at most N immediate emails per hour, the overflow joins the digest;
 *  - people can switch to digest-only or off, and unacknowledged alerts escalate to the next role.
 */
class AlertDispatcher
{
    public const MAX_INSTANT_PER_HOUR = 5;

    /** @return Collection<int,User> */
    public function recipients(Alert $alert, int $level = 0): Collection
    {
        $alert->loadMissing('rule', 'orgUnit');
        $roles = $level === 0 ? ($alert->rule->audience_roles ?? ['district_officer']) : ['national_manager', 'super_admin'];

        return User::role($roles)->where('active', true)->with('orgUnits')->get()
            ->filter(fn (User $u) => $u->canAccessOrgUnit($alert->orgUnit))
            ->reject(fn (User $u) => (AlertPreference::find($u->id)?->mode ?? 'auto') === 'off')
            ->values();
    }

    /** @return int number of immediate emails sent */
    public function notifyNew(Alert $alert): int
    {
        $sent = 0;
        foreach ($this->recipients($alert) as $user) {
            $mode = AlertPreference::find($user->id)?->mode ?? 'auto';
            $instant = $alert->severity === 'high' && $mode === 'auto';
            if ($instant && ! $this->allowInstant($user)) {
                $this->record($alert, $user, 'digest', 'queued', 'Hourly limit reached; included in the next digest');
                continue;
            }
            if ($instant) {
                $this->sendInstant($alert, $user, 'instant');
                $sent++;
            } else {
                $this->record($alert, $user, 'digest', 'queued', null);
            }
        }

        return $sent;
    }

    public function escalate(Alert $alert): int
    {
        $alert->update(['status' => 'escalated', 'escalation_level' => $alert->escalation_level + 1, 'escalated_at' => now()]);
        $sent = 0;
        foreach ($this->recipients($alert, 1) as $user) {
            $this->sendInstant($alert, $user, 'escalation');
            $sent++;
        }

        return $sent;
    }

    /** One digest email per user with everything still queued for them. @return int digests sent */
    public function sendDigests(): int
    {
        $queued = AlertDelivery::where(['mode' => 'digest', 'status' => 'queued'])->with('alert.disease', 'alert.orgUnit')->get()->groupBy('user_id');
        $count = 0;
        foreach ($queued as $userId => $items) {
            $user = User::find($userId);
            $alerts = $items->pluck('alert')->filter(fn ($a) => $a && $a->isActive())->unique('id')->values();
            if (! $user || $alerts->isEmpty()) {
                AlertDelivery::whereIn('id', $items->pluck('id'))->update(['status' => 'sent', 'sent_at' => now(), 'reason' => 'No longer active']);

                continue;
            }
            Mail::to($user)->send(new AlertDigestMail($user, $alerts));
            AlertDelivery::whereIn('id', $items->pluck('id'))->update(['status' => 'sent', 'sent_at' => now()]);
            AlertPreference::updateOrCreate(['user_id' => $user->id], ['last_digest_at' => now()]);
            $count++;
        }

        return $count;
    }

    private function allowInstant(User $user): bool
    {
        $key = 'alert-instant:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, self::MAX_INSTANT_PER_HOUR)) {
            return false;
        }
        RateLimiter::hit($key, 3600);

        return true;
    }

    private function sendInstant(Alert $alert, User $user, string $mode): void
    {
        $delivery = $this->record($alert, $user, $mode, 'queued', null);
        try {
            Mail::to($user)->send(new AlertMail($alert->fresh(['disease', 'orgUnit']), $user, $mode === 'escalation'));
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $delivery->update(['status' => 'failed', 'reason' => mb_substr($e->getMessage(), 0, 120)]);
        }
    }

    private function record(Alert $alert, User $user, string $mode, string $status, ?string $reason): AlertDelivery
    {
        return AlertDelivery::create(['alert_id' => $alert->id, 'user_id' => $user->id, 'channel' => 'mail', 'mode' => $mode, 'status' => $status, 'reason' => $reason]);
    }
}
