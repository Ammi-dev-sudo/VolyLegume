<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class StockMovementPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function create(User $user): bool
    {
        return $this->isMember();
    }

    public function adjust(User $user): bool
    {
        return $this->canManageReferentials();
    }
}
