<?php

namespace App\Policies;

use App\Models\Harvest;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class HarvestPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, Harvest $harvest): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($harvest);
    }

    public function create(User $user): bool
    {
        return $this->isMember();
    }

    public function delete(User $user, Harvest $harvest): bool
    {
        return $this->canManageReferentials() && $this->belongsToCurrentFarm($harvest);
    }
}
