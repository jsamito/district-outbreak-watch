<?php

namespace App\Policies;

use App\Models\CaseRecord;
use App\Models\User;

class CaseRecordPolicy
{
    private function inScope(User $user, CaseRecord $case): bool
    {
        return $user->active && $user->canAccessOrgUnit($case->facility);
    }

    /** Full detail: district officers and clinicians. */
    public function view(User $user, CaseRecord $case): bool
    {
        return $user->can('view cases') && $this->inScope($user, $case);
    }

    /** Pseudonymised detail: epidemiologists. */
    public function viewPseudonymised(User $user, CaseRecord $case): bool
    {
        return $user->can('view cases pseudonymised') && $this->inScope($user, $case);
    }

    public function update(User $user, CaseRecord $case): bool
    {
        return $user->can('edit cases') && $this->inScope($user, $case);
    }

    public function enterLabResult(User $user, CaseRecord $case): bool
    {
        return ($user->can('enter lab results') && $this->inScope($user, $case));
    }
}
