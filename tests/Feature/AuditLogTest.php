<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Input;
use App\Services\TreatmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_une_operation_sensible_cree_des_entrees_audit_avec_acteur_et_exploitation(): void
    {
        $farm = $this->createFarm();
        $user = $this->actingAsFarmMember($farm, FarmRole::Agent);
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $input = Input::factory()->phytosanitary()->create([
            'farm_id' => $farm->id,
            'quantity' => 20,
            'minimum_stock' => 0,
        ]);

        app(TreatmentService::class)->record($campaign, $input, $user, [
            'quantity' => 1,
            'dose_value' => 2,
            'dose_unit' => 'L/ha',
            'pre_harvest_days' => 14,
            'applied_at' => now()->subDay()->toDateTimeString(),
            'delay_source' => 'Notice fabricant',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'action' => 'phytosanitary_treatment.created',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'action' => 'stock_movement.created',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'action' => 'activity.created',
        ]);
    }

    public function test_un_acces_refuse_est_journalise(): void
    {
        $farm = $this->createFarm();
        $user = $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->get(route('expenses.create'))->assertForbidden();

        $log = AuditLog::query()
            ->where('action', 'access.denied')
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($farm->id, $log->farm_id);
        $this->assertSame('GET', $log->context['method']);
    }

    public function test_un_responsable_peut_consulter_le_journal_de_son_exploitation(): void
    {
        $farm = $this->createFarm();
        $user = $this->actingAsFarmMember($farm, FarmRole::Responsable);

        AuditLog::create([
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'action' => 'test.event',
            'description' => 'Événement de test.',
            'created_at' => now(),
        ]);

        $this->get(route('audit.index'))
            ->assertOk()
            ->assertSee('test.event')
            ->assertSee('Événement de test.');
    }
}
