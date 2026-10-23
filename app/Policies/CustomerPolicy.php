<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class CustomerPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($customer);
    }

    public function create(User $user): bool
    {
        return $this->canManageReferentials();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->canManageReferentials() && $this->belongsToCurrentFarm($customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->update($user, $customer);
    }
}
