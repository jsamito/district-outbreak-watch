<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastPoint extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'point' => 'float', 'lower80' => 'float', 'upper80' => 'float', 'lower95' => 'float', 'upper95' => 'float'];
    }
}
