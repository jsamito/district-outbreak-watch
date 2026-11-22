<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataQualityFlag extends Model
{
    /** type => [label, what it means, why it matters] */
    public const TYPES = [
        'missing_reports' => ['Reports missing', 'A reporting facility sent nothing for three weeks in a row.', 'Counts for the area are probably too low.'],
        'late_reporting' => ['Late reporting', 'A facility sends most weekly reports after the deadline.', 'Alerts may fire late for the area.'],
        'coverage_over_100' => ['Coverage above 100%', 'Doses given are more than the people expected in the age group.', 'The population estimate is probably out of date.'],
        'missing_population' => ['No population figure', 'A district has no population for the latest year.', 'Rates and coverage cannot be calculated.'],
        'implausible_jump' => ['Count far above usual', 'A facility count is ten times its recent average.', 'It may be a typing error, or the first sign of an outbreak.'],
        'duplicate_case' => ['Possible duplicate case', 'The same person has two cases of one disease within 14 days.', 'The case may be counted twice.'],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'resolved_at' => 'datetime'];
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('resolved_at');
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $q->whereIn('org_unit_id', $user->scopedOrgUnits()->select('org_units.id'));
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }
}
