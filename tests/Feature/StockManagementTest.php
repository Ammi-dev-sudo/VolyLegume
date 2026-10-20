<?php

namespace Tests\Feature;

use App\Enums\BaseUnit;
use App\Enums\FarmRole;
use App\Enums\InputCategory;
use App\Exceptions\InsufficientStockException;
use App\Models\Input;
use App\Notifications\StockCriticalNotification;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    private function makeInput(array $attributes = []): Input
    {
        $farm = $this->createFarm();

        return Input::factory()->create($attributes + [
            'farm_id' => $farm->id,
            'category' => InputCategory::Fertilizer,
            'base_unit' => BaseUnit::Kg,
            'quantity' => 0,
            'minimum_stock' => 10,
        ]);
    }

    public function test_une_entree_augmente_le_stock_disponible(): void
    {
        $input = $this->makeInput();
        $actor = $this->memberOf($input->farm);

        app(StockService::class)->receive($input, 25, $actor, 'Achat fournisseur');

        $this->assertSame(25.0, (float) $input->fresh()->quantity);
    }

    public function test_une_sortie_superieure_au_stock_est_refusee(): void
    {
        $input = $this->makeInput();
        $actor = $this->memberOf($input->farm);
        app(StockService::class)->receive($input, 5, $actor, 'Achat');

        $this->expectException(InsufficientStockException::class);

        app(StockService::class)->consume($input, 8, $actor, 'Traitement');
    }

    public function test_le_solde_ne_devient_jamais_negatif(): void
    {
        $input = $this->makeInput();
        $actor = $this->memberOf($input->farm);
        app(StockService::class)->receive($input, 5, $actor, 'Achat');

        try {
            app(StockService::class)->consume($input, 8, $actor, 'Traitement');
        } catch (InsufficientStockException) {
            // attendu
        }

        $this->assertSame(5.0, (float) $input->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_le_passage_en_stock_critique_notifie_les_responsables(): void
    {
        Notification::fake();

        $input = $this->makeInput();
        $responsable = $this->memberOf($input->farm, FarmRole::Responsable);
        $agent = $this->memberOf($input->farm, FarmRole::Agent);

        app(StockService::class)->receive($input, 12, $agent, 'Achat');
        app(StockService::class)->consume($input, 3, $agent, 'Consommation');

        Notification::assertSentTo($responsable, StockCriticalNotification::class);
        Notification::assertNotSentTo($agent, StockCriticalNotification::class);
    }

    public function test_un_ajustement_negatif_est_accepte_et_trace(): void
    {
        $input = $this->makeInput();
        $actor = $this->memberOf($input->farm);
        app(StockService::class)->receive($input, 20, $actor, 'Achat');

        app(StockService::class)->adjust($input, -4, $actor, 'Casse');

        $this->assertSame(16.0, (float) $input->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'input_id' => $input->id,
            'type' => 'adjustment',
            'quantity_delta' => -4,
        ]);
    }
}
