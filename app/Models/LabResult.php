<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabResult extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['collected_at' => 'date', 'resulted_at' => 'date'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }
}
