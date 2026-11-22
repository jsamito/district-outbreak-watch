<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseRecord extends Model
{
    protected $table = 'cases';

    protected $guarded = [];

    public const CLASSIFICATIONS = ['suspected', 'probable', 'confirmed', 'discarded'];
    public const OUTCOMES = ['unknown', 'alive', 'recovered', 'died'];
    public const STATUSES = ['reported', 'investigating', 'closed'];

    protected function casts(): array
    {
        return [
            'onset_date' => 'date', 'notification_date' => 'date', 'investigation_date' => 'date', 'epi_week' => 'date',
            'hospitalised' => 'boolean', 'is_synthetic' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'reporting_org_unit_id');
    }

    public function residence(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'residence_org_unit_id');
    }

    public function labResults(): HasMany
    {
        return $this->hasMany(LabResult::class, 'case_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CaseEvent::class, 'case_id')->orderBy('occurred_at');
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class, 'case_id');
    }

    /** Cases reported from facilities inside the user's area. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $q->whereIn('reporting_org_unit_id', $user->scopedOrgUnits()->select('org_units.id'));
    }
}
