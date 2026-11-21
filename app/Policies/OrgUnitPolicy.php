<?php

namespace App\Policies;

use App\Models\OrgUnit;
use App\Models\User;

class OrgUnitPolicy
{
    public function view(User $user, OrgUnit $orgUnit): bool
    {
        return $user->active && $user->canAccessOrgUnit($orgUnit);
    }
}
