<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Encounter extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['encountered_at' => 'datetime'];
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }
}
