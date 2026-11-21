<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrgUnit extends Model
{
    use HasFactory;

    public const COUNTRY = 'country';
    public const REGION = 'region';
    public const DISTRICT = 'district';
    public const FACILITY = 'facility';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'geometry' => 'array',
            'active' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function facilityProfile(): HasOne
    {
        return $this->hasOne(FacilityProfile::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_org_unit');
    }

    /** This unit and everything below it (materialized path prefix match). */
    public function scopeWithin(Builder $query, self $ancestor): Builder
    {
        return $query->where(function (Builder $q) use ($ancestor) {
            $q->where('path', $ancestor->path)
                ->orWhere('path', 'like', self::escapeLike($ancestor->path).'/%');
        });
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function isWithin(self $ancestor): bool
    {
        return $this->path === $ancestor->path || str_starts_with($this->path, $ancestor->path.'/');
    }

    public function district(): ?self
    {
        return match ($this->type) {
            self::DISTRICT => $this,
            self::FACILITY => $this->parent,
            default => null,
        };
    }

    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
