<?php

namespace App\Policies;

use App\Models\ProduceLot;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class ProduceLotPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, ProduceLot $lot): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($lot);
    }

    public function adjust(User $user, ProduceLot $lot): bool
    {
        return $this->canManageReferentials() && $this->belongsToCurrentFarm($lot);
    }
}
