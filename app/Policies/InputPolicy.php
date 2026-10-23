<?php

namespace App\Policies;

use App\Models\Input;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class InputPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, Input $input): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($input);
    }

    public function create(User $user): bool
    {
        return $this->canManageReferentials();
    }

    public function update(User $user, Input $input): bool
    {
        return $this->canManageReferentials() && $this->belongsToCurrentFarm($input);
    }

    public function delete(User $user, Input $input): bool
    {
        return $this->update($user, $input);
    }

    public function adjust(User $user): bool
    {
        return $this->canManageReferentials();
    }
}
