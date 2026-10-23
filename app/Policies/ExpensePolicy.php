<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class ExpensePolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($expense);
    }

    public function create(User $user): bool
    {
        return $this->canManageFinances();
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->canManageFinances() && $this->belongsToCurrentFarm($expense);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense);
    }
}
