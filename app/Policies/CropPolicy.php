<?php

namespace App\Policies;

use App\Models\Crop;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class CropPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, Crop $crop): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($crop);
    }

    public function create(User $user): bool
    {
        return $this->canManageReferentials();
    }

    public function update(User $user, Crop $crop): bool
    {
        return $this->canManageReferentials() && $this->belongsToCurrentFarm($crop);
    }

    public function delete(User $user, Crop $crop): bool
    {
        return $this->update($user, $crop);
    }
}
