<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;

class AlertPolicy
{
    public function view(User $user, Alert $alert): bool
    {
        return $user->active && $user->can('view alerts') && $user->canAccessOrgUnit($alert->orgUnit);
    }

    public function acknowledge(User $user, Alert $alert): bool
    {
        return $this->view($user, $alert) && $user->can('acknowledge alerts') && $alert->isActive();
    }
}
