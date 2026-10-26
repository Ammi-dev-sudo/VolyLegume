<?php

namespace App\Policies;

use App\Models\IrrigationEvent;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class IrrigationEventPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, IrrigationEvent $irrigationEvent): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($irrigationEvent);
    }

    public function create(User $user): bool
    {
        return $this->isMember();
    }

    public function update(User $user, IrrigationEvent $irrigationEvent): bool
    {
        return $this->canManageReferentials() && $this->belongsToCurrentFarm($irrigationEvent);
    }

    public function delete(User $user, IrrigationEvent $irrigationEvent): bool
    {
        return $this->update($user, $irrigationEvent);
    }
}
