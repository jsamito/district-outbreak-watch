<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportingStatus extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['week_start' => 'date', 'received' => 'boolean', 'on_time' => 'boolean', 'received_at' => 'datetime'];
    }
}
