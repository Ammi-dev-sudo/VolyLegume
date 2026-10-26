<?php

namespace Tests\Feature;

use App\Models\ProduceLot;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class TraceabilityQrTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_la_tracabilite_est_accessible_sans_authentification(): void
    {
        $farm = $this->createFarm();
        $lot = ProduceLot::factory()->create(['farm_id' => $farm->id, 'code' => 'LOT-PUBLIC']);

        $this->get(route('traceability.show', ['token' => $lot->trace_token]))
            ->assertOk()
            ->assertSee('LOT-PUBLIC')
            ->assertSee('Traçabilité publique')
            ->assertSee($farm->name);
    }

    public function test_un_token_inconnu_renvoie_404(): void
    {
        $this->get(route('traceability.show', ['token' => 'inconnu']))->assertNotFound();
    }

    public function test_le_qr_code_genere_un_png_et_un_lien_public(): void
    {
        Storage::fake('public');
        $farm = $this->createFarm();
        $lot = ProduceLot::factory()->create(['farm_id' => $farm->id]);
        $this->actingAsFarmMember($farm);

        $this->post(route('produce-lots.qr.generate', $lot))
            ->assertRedirect(route('produce-lots.show', $lot));

        $lot->refresh();

        $this->assertNotNull($lot->trace_token);
        $this->assertNotNull($lot->qr_code_path);
        $this->assertStringEndsWith('.png', $lot->qr_code_path);
        Storage::disk('public')->assertExists($lot->qr_code_path);

        $this->get(route('produce-lots.show', $lot))->assertOk()->assertSee('Traçabilité & QR');
    }

    public function test_le_service_qr_ne_regenere_pas_un_fichier_existant(): void
    {
        Storage::fake('public');
        $farm = $this->createFarm();
        $lot = ProduceLot::factory()->create(['farm_id' => $farm->id]);
        $service = app(QrCodeService::class);

        $first = $service->ensureForLot($lot);
        $second = $service->ensureForLot($first->fresh());

        $this->assertSame($first->qr_code_path, $second->qr_code_path);
        Storage::disk('public')->assertExists($second->qr_code_path);
    }
}
