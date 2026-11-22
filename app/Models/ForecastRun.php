<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForecastRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['params' => 'array', 'backtest' => 'array', 'trained_through' => 'date'];
    }

    public function points(): HasMany
    {
        return $this->hasMany(ForecastPoint::class)->orderBy('period_start');
    }
}
