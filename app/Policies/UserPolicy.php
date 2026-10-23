<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function manage(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }
}
