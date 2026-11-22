<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'last_seen_at' => 'datetime'];
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    /** Creates a client and returns [client, plain token]. The token is shown once and only its hash is stored. */
    public static function issue(string $name, OrgUnit $unit): array
    {
        $token = 'ow_'.Str::random(40);
        $client = static::create(['name' => $name, 'org_unit_id' => $unit->id, 'token_hash' => hash('sha256', $token)]);

        return [$client, $token];
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token_hash', hash('sha256', $token))->where('active', true)->first();
    }
}
