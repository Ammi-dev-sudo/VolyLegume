<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use App\Enums\FarmStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_un_responsable_peut_rattacher_un_compte_existant(): void
    {
        $farm = $this->createFarm();
        $responsable = $this->actingAsFarmMember($farm, FarmRole::Responsable);
        $farm->update(['manager_id' => $responsable->id]);

        $newMember = User::factory()->create(['is_active' => true]);

        $this->post(route('team.store'), [
            'email' => $newMember->email,
            'role' => FarmRole::Agent->value,
        ])->assertRedirect(route('team.index'));

        $this->assertDatabaseHas('farm_user', [
            'farm_id' => $farm->id,
            'user_id' => $newMember->id,
            'role' => FarmRole::Agent->value,
            'is_active' => true,
        ]);
    }

    public function test_un_agent_ne_peut_pas_gerer_l_equipe(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->get(route('team.index'))->assertForbidden();
        $this->post(route('team.store'), [
            'email' => 'autre@exemple.mg',
            'role' => FarmRole::Agent->value,
        ])->assertForbidden();
    }

    public function test_un_compte_inconnu_ou_inactif_est_refuse(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);

        $this->post(route('team.store'), [
            'email' => 'inconnu@exemple.mg',
            'role' => FarmRole::Agent->value,
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('farm_user', 1);
    }

    public function test_un_compte_deja_rattache_est_refuse(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);
        $existing = $this->memberOf($farm, FarmRole::Agent);

        $this->post(route('team.store'), [
            'email' => $existing->email,
            'role' => FarmRole::Agent->value,
        ])->assertSessionHasErrors('email');
    }

    public function test_un_rattachement_inactif_est_reactive_avec_le_nouveau_role(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);
        $inactive = $this->memberOf($farm, FarmRole::Agent);
        $inactive->memberships()->update(['is_active' => false]);

        $this->post(route('team.store'), [
            'email' => $inactive->email,
            'role' => FarmRole::Responsable->value,
        ])->assertRedirect(route('team.index'));

        $this->assertDatabaseHas('farm_user', [
            'farm_id' => $farm->id,
            'user_id' => $inactive->id,
            'role' => FarmRole::Responsable->value,
            'is_active' => true,
        ]);
    }

    public function test_le_responsable_principal_ne_peut_pas_etre_desactive(): void
    {
        $farm = $this->createFarm();
        $responsable = $this->actingAsFarmMember($farm, FarmRole::Responsable);
        $farm->update(['manager_id' => $responsable->id]);

        $managerMembership = $responsable->memberships()->where('farm_id', $farm->id)->first();

        $this->delete(route('team.destroy', $managerMembership))
            ->assertSessionHasErrors('membership');

        $this->assertDatabaseHas('farm_user', [
            'id' => $managerMembership->id,
            'is_active' => true,
        ]);
    }

    public function test_un_membre_peut_etre_desactive(): void
    {
        $farm = $this->createFarm();
        $responsable = $this->actingAsFarmMember($farm, FarmRole::Responsable);
        $farm->update(['manager_id' => $responsable->id]);

        $agent = $this->memberOf($farm, FarmRole::Agent);
        $membership = $agent->memberships()->where('farm_id', $farm->id)->first();

        $this->delete(route('team.destroy', $membership))->assertRedirect(route('team.index'));

        $this->assertDatabaseHas('farm_user', [
            'id' => $membership->id,
            'is_active' => false,
        ]);
    }

    public function test_on_ne_peut_pas_desactiver_son_propre_rattachement(): void
    {
        $farm = $this->createFarm();
        $manager = $this->memberOf($farm, FarmRole::Responsable);
        $farm->update(['manager_id' => $manager->id]);

        $responsable = $this->actingAsFarmMember($farm, FarmRole::Responsable);
        $ownMembership = $responsable->memberships()->where('farm_id', $farm->id)->first();

        $this->delete(route('team.destroy', $ownMembership))
            ->assertSessionHasErrors('membership');
    }

    public function test_le_role_du_responsable_principal_ne_peut_pas_etre_change(): void
    {
        $farm = $this->createFarm();
        $responsable = $this->actingAsFarmMember($farm, FarmRole::Responsable);
        $farm->update(['manager_id' => $responsable->id]);

        $managerMembership = $responsable->memberships()->where('farm_id', $farm->id)->first();

        $this->put(route('team.update', $managerMembership), [
            'role' => FarmRole::Agent->value,
        ])->assertSessionHasErrors('role');
    }

    public function test_le_role_d_un_membre_ordinaire_peut_etre_change(): void
    {
        $farm = $this->createFarm();
        $responsable = $this->actingAsFarmMember($farm, FarmRole::Responsable);
        $farm->update(['manager_id' => $responsable->id]);

        $agent = $this->memberOf($farm, FarmRole::Agent);
        $membership = $agent->memberships()->where('farm_id', $farm->id)->first();

        $this->put(route('team.update', $membership), [
            'role' => FarmRole::Responsable->value,
        ])->assertRedirect(route('team.index'));

        $this->assertDatabaseHas('farm_user', [
            'id' => $membership->id,
            'role' => FarmRole::Responsable->value,
        ]);
    }

    public function test_l_edition_de_lexploitation_est_reservee_aux_responsables(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->get(route('farms.edit', $farm))->assertForbidden();
        $this->put(route('farms.update', $farm), [
            'name' => 'Autre nom',
            'location' => 'Ailleurs',
            'area_m2' => 1000,
            'status' => FarmStatus::Active->value,
        ])->assertForbidden();
    }

    public function test_un_responsable_peut_modifier_lexploitation(): void
    {
        $farm = $this->createFarm();
        $responsable = $this->actingAsFarmMember($farm, FarmRole::Responsable);
        $farm->update(['manager_id' => $responsable->id]);

        $this->put(route('farms.update', $farm), [
            'name' => 'Ferme Renommée',
            'location' => 'Antsirabe',
            'area_m2' => 2500,
            'status' => FarmStatus::Active->value,
            'manager_id' => $responsable->id,
        ])->assertRedirect(route('farms.index'));

        $this->assertDatabaseHas('farms', [
            'id' => $farm->id,
            'name' => 'Ferme Renommée',
            'location' => 'Antsirabe',
        ]);
    }

    public function test_l_activation_exige_un_responsable_rattache(): void
    {
        $farm = $this->createFarm(['status' => FarmStatus::Draft->value]);
        $responsable = $this->actingAsFarmMember($farm, FarmRole::Responsable);
        $farm->refresh();

        $this->put(route('farms.update', $farm), [
            'name' => $farm->name,
            'location' => $farm->location,
            'area_m2' => $farm->area_m2,
            'status' => FarmStatus::Active->value,
        ])->assertSessionHasErrors('manager_id');
    }
}
