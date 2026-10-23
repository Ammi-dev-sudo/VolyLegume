<?php

namespace App\Policies;

use App\Models\PhytosanitaryTreatment;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class TreatmentPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, PhytosanitaryTreatment $treatment): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($treatment);
    }

    public function create(User $user): bool
    {
        return $this->isMember();
    }
}
