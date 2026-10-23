<?php

namespace App\Policies\Concerns;

use App\Enums\FarmRole;
use App\Support\FarmContext;
use Illuminate\Database\Eloquent\Model;

trait ChecksFarmRole
{
    protected function currentRole(): ?FarmRole
    {
        return app(FarmContext::class)->role();
    }

    protected function isMember(): bool
    {
        return $this->currentRole() !== null;
    }

    protected function canManageReferentials(): bool
    {
        return $this->currentRole()?->canManageReferentials() ?? false;
    }

    protected function canManageFinances(): bool
    {
        return $this->currentRole()?->canManageFinances() ?? false;
    }

    protected function belongsToCurrentFarm(Model $model): bool
    {
        return (int) $model->getAttribute('farm_id') === (int) app(FarmContext::class)->id();
    }
}
