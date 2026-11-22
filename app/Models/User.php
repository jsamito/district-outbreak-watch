<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'job_title', 'active', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Org units this user is assigned to (their scope roots). */
    public function orgUnits(): BelongsToMany
    {
        return $this->belongsToMany(OrgUnit::class, 'user_org_unit');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /** Org units in scope: every assigned unit and everything below it. */
    public function scopedOrgUnits(): Builder
    {
        $roots = $this->orgUnits;

        return OrgUnit::query()->where(function (Builder $q) use ($roots) {
            if ($roots->isEmpty()) {
                $q->whereRaw('1 = 0');

                return;
            }
            foreach ($roots as $root) {
                $q->orWhere(function (Builder $inner) use ($root) {
                    $inner->where('path', $root->path)
                        ->orWhere('path', 'like', OrgUnit::escapeLike($root->path).'/%');
                });
            }
        });
    }

    public function canAccessOrgUnit(OrgUnit $unit): bool
    {
        return $this->orgUnits->contains(fn (OrgUnit $root) => $unit->isWithin($root));
    }

    /** True when the user is assigned to a country-level unit. */
    public function hasNationalScope(): bool
    {
        return $this->orgUnits->contains(fn (OrgUnit $o) => $o->type === OrgUnit::COUNTRY);
    }
}
