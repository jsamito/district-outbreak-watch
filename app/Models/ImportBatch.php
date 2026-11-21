<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id');
    }

    public static function begin(string $command, ?DataSource $source = null): self
    {
        return static::create(['command' => $command, 'data_source_id' => $source?->id, 'started_at' => now(), 'status' => 'running']);
    }

    public function finish(int $rowsIn, int $rejected = 0, ?string $notes = null): void
    {
        $this->update(['finished_at' => now(), 'rows_in' => $rowsIn, 'rows_rejected' => $rejected, 'status' => 'done', 'notes' => $notes]);
    }
}
