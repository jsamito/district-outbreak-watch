<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vaccination extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['administered_on' => 'date'];
    }

    public function antigen(): BelongsTo
    {
        return $this->belongsTo(Antigen::class);
    }
}
