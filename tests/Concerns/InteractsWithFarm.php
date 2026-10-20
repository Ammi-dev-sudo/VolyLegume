<?php

namespace Tests\Concerns;

use App\Enums\FarmRole;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\User;

trait InteractsWithFarm
{
    protected function createFarm(array $attributes = []): Farm
    {
        return Farm::factory()->create($attributes);
    }

    protected function memberOf(Farm $farm, FarmRole $role = FarmRole::Responsable): User
    {
        $user = User::factory()->create(['is_active' => true]);

        FarmUser::create([
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_active' => true,
        ]);

        return $user;
    }

    /**
     * Authentifie un membre de l'exploitation et active son contexte.
     */
    protected function actingAsFarmMember(Farm $farm, FarmRole $role = FarmRole::Responsable): User
    {
        $user = $this->memberOf($farm, $role);

        $this->actingAs($user);
        $this->withSession(['active_farm_id' => $farm->id]);

        return $user;
    }
}
