<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertRule extends Model
{
    public const METHODS = ['outbreak_signal' => 'Outbreak signal (above baseline)', 'single_case' => 'Single case (immediately notifiable)',
        'threshold' => 'Fixed threshold (cases in a week)', 'cluster' => 'Cluster (cases close in time in one district)'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['params' => 'array', 'audience_roles' => 'array', 'enabled' => 'boolean'];
    }

    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
