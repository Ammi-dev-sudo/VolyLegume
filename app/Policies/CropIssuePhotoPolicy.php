<?php

namespace App\Policies;

use App\Models\CropIssuePhoto;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class CropIssuePhotoPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, CropIssuePhoto $photo): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($photo);
    }

    public function create(User $user): bool
    {
        return $this->isMember();
    }

    public function delete(User $user, CropIssuePhoto $photo): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($photo);
    }
}
