<?php

namespace App\Policies;

use App\Models\ProductionUnitZone;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class ProductionUnitZonePolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, ProductionUnitZone $zone): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($zone);
    }

    public function create(User $user): bool
    {
        return $this->canManageReferentials();
    }

    public function update(User $user, ProductionUnitZone $zone): bool
    {
        return $this->canManageReferentials() && $this->belongsToCurrentFarm($zone);
    }

    public function delete(User $user, ProductionUnitZone $zone): bool
    {
        return $this->update($user, $zone);
    }
}
