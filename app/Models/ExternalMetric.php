<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalMetric extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['period_start' => 'date'];
    }
}
