<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A short note pinned to a week on a chart, such as "Mass vaccination campaign started". */
class Annotation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['period_start' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    /** Notes made for the area or anything inside it, for one disease or for all diseases. */
    public function scopeFor(Builder $q, OrgUnit $area, ?Disease $disease): Builder
    {
        return $q->whereIn('org_unit_id', OrgUnit::within($area)->select('id'))
            ->when($disease, fn ($w) => $w->where(fn ($x) => $x->whereNull('disease_id')->orWhere('disease_id', $disease->id)));
    }
}
