<?php

namespace App\Policies;

use App\Models\ProductionUnitPhoto;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class ProductionUnitPhotoPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, ProductionUnitPhoto $productionUnitPhoto): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($productionUnitPhoto);
    }

    public function create(User $user): bool
    {
        return $this->isMember();
    }

    public function update(User $user, ProductionUnitPhoto $productionUnitPhoto): bool
    {
        return $this->canManageReferentials() && $this->belongsToCurrentFarm($productionUnitPhoto);
    }

    public function delete(User $user, ProductionUnitPhoto $productionUnitPhoto): bool
    {
        return $this->update($user, $productionUnitPhoto);
    }
}
