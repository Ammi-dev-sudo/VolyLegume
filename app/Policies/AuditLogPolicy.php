<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRole;
use App\Support\FarmContext;

class AuditLogPolicy
{
    use ChecksFarmRole;

    public function viewAny(User $user): bool
    {
        return $this->canManageReferentials();
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->canManageReferentials()
            && (int) $auditLog->farm_id === (int) app(FarmContext::class)->id();
    }
}
