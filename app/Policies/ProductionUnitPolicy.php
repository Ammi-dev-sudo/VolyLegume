<?php

namespace App\Policies;

use App\Models\ProductionUnit;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class ProductionUnitPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, ProductionUnit $productionUnit): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($productionUnit);
    }

    public function create(User $user): bool
    {
        return $this->canManageReferentials();
    }

    public function update(User $user, ProductionUnit $productionUnit): bool
    {
        return $this->canManageReferentials() && $this->belongsToCurrentFarm($productionUnit);
    }

    public function delete(User $user, ProductionUnit $productionUnit): bool
    {
        return $this->update($user, $productionUnit);
    }
}
