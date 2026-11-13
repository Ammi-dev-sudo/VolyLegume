<?php

namespace App\Policies;

use App\Models\CampaignStageEvent;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class CampaignStageEventPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, CampaignStageEvent $event): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($event);
    }

    public function create(User $user): bool
    {
        return $this->isMember();
    }

    public function delete(User $user, CampaignStageEvent $event): bool
    {
        return $this->canManageReferentials() && $this->belongsToCurrentFarm($event);
    }
}
