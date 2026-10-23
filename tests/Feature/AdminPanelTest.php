<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use App\Enums\FarmStatus;
use App\Models\FarmUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_un_utilisateur_ordinaire_ne_peut_pas_acceder_a_l_administration(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_un_administrateur_plateforme_accede_aux_ecrans_de_supervision(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $farm = $this->createFarm();
        $this->memberOf($farm, FarmRole::Responsable);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Administration de la plateforme');

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.farms.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.farms.show', $farm->id))->assertOk();
        $this->actingAs($admin)->get(route('admin.audit.index'))->assertOk();
    }

    public function test_un_administrateur_peut_desactiver_un_autre_compte(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $target = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $target))
            ->assertRedirect();

        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_un_administrateur_ne_peut_pas_desactiver_son_propre_compte(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.show', $admin))
            ->patch(route('admin.users.status', $admin))
            ->assertSessionHasErrors('user');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_un_administrateur_peut_creer_un_compte_et_le_rattacher(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $farm = $this->createFarm();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Nouveau Technicien',
            'email' => 'technicien.nouveau@volylegume.mg',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'farm_id' => $farm->id,
            'role' => FarmRole::Agent->value,
        ])->assertRedirect();

        $created = User::query()->where('email', 'technicien.nouveau@volylegume.mg')->firstOrFail();

        $this->assertDatabaseHas('farm_user', [
            'farm_id' => $farm->id,
            'user_id' => $created->id,
            'role' => FarmRole::Agent->value,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.user.created']);
    }

    public function test_un_administrateur_peut_modifier_un_rattachement(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $farm = $this->createFarm();
        $member = $this->memberOf($farm, FarmRole::Agent);
        $membership = FarmUser::query()->where('user_id', $member->id)->firstOrFail();

        $this->actingAs($admin)->put(route('admin.users.memberships.update', [$member, $membership]), [
            'role' => FarmRole::Responsable->value,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('farm_user', [
            'id' => $membership->id,
            'role' => FarmRole::Responsable->value,
            'is_active' => true,
        ]);
    }

    public function test_un_administrateur_peut_changer_le_statut_d_une_exploitation(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $farm = $this->createFarm(['status' => FarmStatus::Active->value]);

        $this->actingAs($admin)
            ->patch(route('admin.farms.status', $farm->id))
            ->assertRedirect();

        $this->assertSame(FarmStatus::Inactive, $farm->fresh()->status);
    }

    public function test_un_administrateur_ne_peut_pas_retirer_son_propre_role_plateforme(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.show', $admin))
            ->patch(route('admin.users.platform-admin', $admin))
            ->assertSessionHasErrors('user');

        $this->assertTrue($admin->fresh()->isPlatformAdmin());
    }
}
