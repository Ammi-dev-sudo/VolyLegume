<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\User;

class FarmPolicy
{
    public function view(User $user, Farm $farm): bool
    {
        return $farm->roleOf($user) !== null;
    }

    public function update(User $user, Farm $farm): bool
    {
        return $farm->roleOf($user)?->canManageTeam() ?? false;
    }

    public function manageTeam(User $user, Farm $farm): bool
    {
        return $farm->roleOf($user)?->canManageTeam() ?? false;
    }
}
