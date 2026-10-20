<?php

namespace App\Support;

use App\Enums\FarmRole;
use App\Models\Farm;

/**
 * Contexte d'exploitation actif pour la requête courante.
 *
 * L'exploitation n'est jamais déduite d'un identifiant envoyé par le client :
 * elle provient d'un rattachement autorisé et est résolue par le middleware
 * `ResolveFarmContext`.
 */
class FarmContext
{
    private ?Farm $farm = null;

    private ?FarmRole $role = null;

    public function set(?Farm $farm, ?FarmRole $role = null): void
    {
        $this->farm = $farm;
        $this->role = $role;
    }

    public function farm(): ?Farm
    {
        return $this->farm;
    }

    public function id(): ?int
    {
        return $this->farm?->id;
    }

    public function role(): ?FarmRole
    {
        return $this->role;
    }

    public function hasRole(FarmRole ...$roles): bool
    {
        return $this->role !== null && in_array($this->role, $roles, true);
    }
}
