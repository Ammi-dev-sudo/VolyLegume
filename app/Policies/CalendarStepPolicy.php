<?php

namespace App\Policies;

use App\Models\CalendarStep;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;

class CalendarStepPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember();
    }

    public function view(User $user, CalendarStep $calendarStep): bool
    {
        return $this->isMember() && $this->belongsToCurrentFarm($calendarStep);
    }

    public function create(User $user): bool
    {
        return $this->canManageReferentials();
    }

    public function update(User $user, CalendarStep $calendarStep): bool
    {
        return $this->canManageReferentials() && $this->belongsToCurrentFarm($calendarStep);
    }

    public function delete(User $user, CalendarStep $calendarStep): bool
    {
        return $this->update($user, $calendarStep);
    }
}
