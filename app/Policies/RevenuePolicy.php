<?php

namespace App\Policies;

use App\Models\Revenue;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class RevenuePolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, Revenue $revenue): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($revenue);
    }

    public function create(User $user): bool
    {
        return $this->canManageFinances();
    }

    public function update(User $user, Revenue $revenue): bool
    {
        return $this->canManageFinances() && $this->belongsToCurrentFarm($revenue);
    }

    public function delete(User $user, Revenue $revenue): bool
    {
        return $this->update($user, $revenue);
    }
}
