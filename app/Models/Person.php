<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    protected $table = 'persons';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_synthetic' => 'boolean'];
    }

    public function residence(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'residence_org_unit_id');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    public function ageOn(\DateTimeInterface $date): int
    {
        return max(0, (int) $date->format('Y') - $this->birth_year);
    }

    public static function ageGroup(int $age): string
    {
        return match (true) {
            $age < 1 => '0-11 months', $age < 5 => '1-4 years', $age < 15 => '5-14 years', $age < 25 => '15-24 years',
            $age < 45 => '25-44 years', $age < 65 => '45-64 years', default => '65+ years',
        };
    }
}
