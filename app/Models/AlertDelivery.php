<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
