<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Exceptions\InsufficientProduceStockException;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\ProduceLot;
use App\Services\HarvestService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class CommercialChainTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    private function scenario(float $quantity = 100): array
    {
        $farm = $this->createFarm();
        $actor = $this->memberOf($farm);
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);

        $harvest = app(HarvestService::class)->record($campaign, $actor, [
            'quantity' => $quantity,
            'harvested_at' => now()->subHour()->toDateTimeString(),
            'quality' => 'bien',
        ]);

        return [$farm, $actor, $campaign, $harvest, $harvest->produceLot];
    }

    public function test_une_recolte_cree_un_lot_et_un_mouvement_entrant(): void
    {
        [, , , $harvest, $lot] = $this->scenario(120);

        $this->assertInstanceOf(ProduceLot::class, $lot);
        $this->assertSame(120.0, (float) $lot->initial_quantity);
        $this->assertSame(120.0, (float) $lot->available_quantity);
        $this->assertSame($harvest->id, $lot->harvest_id);
        $this->assertDatabaseHas('produce_movements', [
            'produce_lot_id' => $lot->id,
            'type' => 'harvest',
            'quantity_delta' => 120,
        ]);
    }

    public function test_une_vente_decremente_le_stock_cree_la_recette_et_suit_les_paiements(): void
    {
        [$farm, $actor, , , $lot] = $this->scenario(100);
        $customer = Customer::factory()->create(['farm_id' => $farm->id]);

        $sale = app(SaleService::class)->create($farm->id, [
            'customer_id' => $customer->id,
            'sale_date' => now()->toDateString(),
            'discount_amount' => 5000,
        ], [
            [
                'produce_lot_id' => $lot->id,
                'quantity' => 25,
                'unit_price' => 2000,
            ],
        ], $actor);

        $this->assertSame(50000.0, (float) $sale->subtotal);
        $this->assertSame(45000.0, (float) $sale->total_amount);
        $this->assertSame(75.0, (float) $lot->fresh()->available_quantity);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'produce_lot_id' => $lot->id,
            'quantity' => 25,
        ]);
        $this->assertDatabaseHas('produce_movements', [
            'produce_lot_id' => $lot->id,
            'sale_id' => $sale->id,
            'type' => 'sale',
            'quantity_delta' => -25,
        ]);
        $this->assertDatabaseHas('revenues', [
            'sale_id' => $sale->id,
            'amount' => 45000,
        ]);

        app(SaleService::class)->recordPayment($sale, [
            'amount' => 15000,
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
        ], $actor);

        $sale->refresh();
        $this->assertSame(PaymentStatus::Partial, $sale->payment_status);
        $this->assertSame(15000.0, (float) $sale->amount_paid);

        app(SaleService::class)->recordPayment($sale, [
            'amount' => 30000,
            'method' => 'mobile_money',
            'paid_at' => now()->toDateString(),
        ], $actor);

        $sale->refresh();
        $this->assertSame(PaymentStatus::Paid, $sale->payment_status);
        $this->assertSame(0.0, $sale->balanceDue());
    }

    public function test_une_vente_superieure_au_stock_est_refusee(): void
    {
        [$farm, $actor, , , $lot] = $this->scenario(10);

        try {
            app(SaleService::class)->create($farm->id, [
                'sale_date' => now()->toDateString(),
            ], [
                [
                    'produce_lot_id' => $lot->id,
                    'quantity' => 11,
                    'unit_price' => 2000,
                ],
            ], $actor);

            $this->fail('La vente aurait dû être refusée.');
        } catch (InsufficientProduceStockException) {
            $this->assertDatabaseCount('sales', 0);
            $this->assertSame(10.0, (float) $lot->fresh()->available_quantity);
        }
    }

    public function test_annuler_une_vente_restaure_le_stock_et_supprime_la_recette(): void
    {
        [$farm, $actor, , , $lot] = $this->scenario(50);

        $sale = app(SaleService::class)->create($farm->id, [
            'sale_date' => now()->toDateString(),
        ], [
            [
                'produce_lot_id' => $lot->id,
                'quantity' => 20,
                'unit_price' => 1500,
            ],
        ], $actor);

        app(SaleService::class)->cancel($sale, $actor);

        $sale->refresh();
        $this->assertSame(SaleStatus::Cancelled, $sale->status);
        $this->assertSame(50.0, (float) $lot->fresh()->available_quantity);
        $this->assertDatabaseMissing('revenues', ['sale_id' => $sale->id]);
        $this->assertDatabaseHas('produce_movements', [
            'produce_lot_id' => $lot->id,
            'sale_id' => $sale->id,
            'type' => 'return',
            'quantity_delta' => 20,
        ]);
    }

    public function test_un_agent_ne_peut_pas_creer_de_vente(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->get(route('sales.create'))->assertForbidden();
        $this->post(route('sales.store'), [
            'sale_date' => now()->toDateString(),
            'items' => [
                ['produce_lot_id' => 1, 'quantity' => 1, 'unit_price' => 1000],
            ],
        ])->assertForbidden();
    }

    public function test_un_lot_d_une_autre_exploitation_est_refuse(): void
    {
        [$farm, $actor] = $this->scenario(30);
        $otherFarm = $this->createFarm();
        $otherActor = $this->memberOf($otherFarm);
        $otherCampaign = Campaign::factory()->create(['farm_id' => $otherFarm->id]);
        $otherHarvest = app(HarvestService::class)->record($otherCampaign, $otherActor, [
            'quantity' => 20,
            'harvested_at' => now()->subHour()->toDateTimeString(),
        ]);

        $this->expectException(ValidationException::class);

        app(SaleService::class)->create($farm->id, [
            'sale_date' => now()->toDateString(),
        ], [
            [
                'produce_lot_id' => $otherHarvest->produceLot->id,
                'quantity' => 5,
                'unit_price' => 1000,
            ],
        ], $actor);
    }

    public function test_les_ecrans_du_stock_et_des_ventes_sont_accessibles(): void
    {
        [$farm, $actor, , $harvest, $lot] = $this->scenario(40);
        $customer = Customer::factory()->create(['farm_id' => $farm->id]);

        $sale = app(SaleService::class)->create($farm->id, [
            'customer_id' => $customer->id,
            'sale_date' => now()->toDateString(),
        ], [
            [
                'produce_lot_id' => $lot->id,
                'quantity' => 5,
                'unit_price' => 2500,
            ],
        ], $actor);

        $this->actingAs($actor);
        $this->withSession(['active_farm_id' => $farm->id]);

        $this->get(route('produce-lots.index'))->assertOk()->assertSee($lot->code);
        $this->get(route('produce-lots.show', $lot))->assertOk()->assertSee($harvest->campaign->name);
        $this->get(route('sales.index'))->assertOk()->assertSee($sale->reference);
        $this->get(route('sales.show', $sale))->assertOk()->assertSee($customer->name);
        $this->get(route('customers.index'))->assertOk()->assertSee($customer->name);
    }
}
