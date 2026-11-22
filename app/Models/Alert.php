<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alert extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'last_signal_week' => 'date', 'triggered_at' => 'datetime', 'escalated_at' => 'datetime',
            'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime', 'observed' => 'float', 'expected' => 'float', 'upper_limit' => 'float',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class);
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(AlertDelivery::class);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $q->whereIn('org_unit_id', $user->scopedOrgUnits()->select('org_units.id'));
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', ['open', 'acknowledged', 'escalated']);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['open', 'acknowledged', 'escalated'], true);
    }
}
