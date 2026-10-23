<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class SalePolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, Sale $sale): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($sale);
    }

    public function create(User $user): bool
    {
        return $this->canManageFinances();
    }

    public function recordPayment(User $user, Sale $sale): bool
    {
        return $this->canManageFinances() && $this->belongsToCurrentFarm($sale);
    }

    public function cancel(User $user, Sale $sale): bool
    {
        return $this->canManageFinances() && $this->belongsToCurrentFarm($sale);
    }
}
