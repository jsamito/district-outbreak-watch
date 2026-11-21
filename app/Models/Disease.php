<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disease extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['immediate_notifiable' => 'boolean', 'case_based' => 'boolean'];
    }
}
