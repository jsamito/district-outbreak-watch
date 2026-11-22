<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertPreference extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_digest_at' => 'datetime'];
    }
}
