<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use App\Models\Input;
use App\Notifications\StockCriticalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

/**
 * Les alertes sont enregistrées avec l'exploitation dans la colonne dédiée
 * `farm_id` : la liste des alertes est filtrée par exploitation sans passer
 * par l'opérateur JSON (non supporté sur une colonne texte).
 */
class NotificationFarmScopeTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_l_alerte_enregistre_l_exploitation_dans_la_colonne_dediee(): void
    {
        $farm = $this->createFarm();
        $user = $this->memberOf($farm, FarmRole::Responsable);
        $input = Input::factory()->create(['farm_id' => $farm->id]);

        $user->notify(new StockCriticalNotification($input));

        $notification = $user->notifications()->firstOrFail();

        $this->assertSame($farm->id, (int) $notification->farm_id);
    }

    public function test_la_liste_des_alertes_ignore_les_autres_exploitations(): void
    {
        $farmA = $this->createFarm();
        $farmB = $this->createFarm();

        $userA = $this->memberOf($farmA, FarmRole::Responsable);
        $userB = $this->memberOf($farmB, FarmRole::Responsable);

        $inputA = Input::factory()->create(['farm_id' => $farmA->id]);
        $inputB = Input::factory()->create(['farm_id' => $farmB->id]);

        $userA->notify(new StockCriticalNotification($inputA));
        $userA->notify(new StockCriticalNotification($inputB));

        $this->actingAs($userA);
        $this->withSession(['active_farm_id' => $farmA->id]);

        $response = $this->get(route('notifications.index'))->assertOk();

        $this->assertSame(1, $response->viewData('notifications')->total());
    }

    public function test_tout_marquer_comme_lu_reste_limite_a_l_exploitation_active(): void
    {
        $farmA = $this->createFarm();
        $farmB = $this->createFarm();

        $userA = $this->memberOf($farmA, FarmRole::Responsable);
        $userB = $this->memberOf($farmB, FarmRole::Responsable);

        $inputA = Input::factory()->create(['farm_id' => $farmA->id]);
        $inputB = Input::factory()->create(['farm_id' => $farmB->id]);

        $userA->notify(new StockCriticalNotification($inputA));
        $userA->notify(new StockCriticalNotification($inputB));

        $this->actingAs($userA);
        $this->withSession(['active_farm_id' => $farmA->id]);

        $this->post(route('notifications.readAll'))->assertRedirect(route('notifications.index'));

        $this->assertNotNull($userA->notifications()->where('farm_id', $farmA->id)->firstOrFail()->read_at);
        $this->assertNull($userA->notifications()->where('farm_id', $farmB->id)->firstOrFail()->read_at);
    }

    public function test_une_alerte_d_une_autre_exploitation_ne_peut_pas_etre_marquee_lue(): void
    {
        $farmA = $this->createFarm();
        $farmB = $this->createFarm();

        $userA = $this->memberOf($farmA, FarmRole::Responsable);
        $inputB = Input::factory()->create(['farm_id' => $farmB->id]);

        $userA->notify(new StockCriticalNotification($inputB));

        $foreignNotification = $userA->notifications()->firstOrFail();

        $this->actingAs($userA);
        $this->withSession(['active_farm_id' => $farmA->id]);

        $this->post(route('notifications.read', $foreignNotification->id))->assertNotFound();

        $this->assertNull($foreignNotification->fresh()->read_at);
    }
}
