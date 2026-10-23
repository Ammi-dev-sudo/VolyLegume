<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class ActivityPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($activity);
    }

    public function create(User $user): bool
    {
        return $this->isMember();
    }

    public function update(User $user, Activity $activity): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($activity);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity);
    }
}
