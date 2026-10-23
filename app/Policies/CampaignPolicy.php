<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class CampaignPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($campaign);
    }

    public function create(User $user): bool
    {
        return $this->canManageReferentials();
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $this->canManageReferentials() && $this->belongsToCurrentFarm($campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->update($user, $campaign);
    }
}
