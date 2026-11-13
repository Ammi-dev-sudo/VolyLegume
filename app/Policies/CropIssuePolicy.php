<?php

namespace App\Policies;

use App\Models\CropIssue;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class CropIssuePolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, CropIssue $cropIssue): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($cropIssue);
    }

    public function create(User $user): bool
    {
        return $this->isMember();
    }

    public function update(User $user, CropIssue $cropIssue): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($cropIssue);
    }

    public function delete(User $user, CropIssue $cropIssue): bool
    {
        return $this->canManageReferentials() && $this->belongsToCurrentFarm($cropIssue);
    }
}
