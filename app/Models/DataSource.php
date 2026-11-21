<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataSource extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['accessed_at' => 'date', 'synthetic' => 'boolean'];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }
}
