<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class AuditRetentionTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    private function log(string $action, \DateTimeInterface $createdAt, ?int $farmId = null): AuditLog
    {
        return AuditLog::create([
            'farm_id' => $farmId,
            'user_id' => null,
            'action' => $action,
            'description' => 'Événement de test',
            'created_at' => $createdAt,
        ]);
    }

    public function test_les_evenements_courants_anciens_sont_purges_et_les_recents_conserves(): void
    {
        $old = $this->log('auth.login', now()->subDays(400));
        $recent = $this->log('auth.login', now()->subDays(10));

        $this->artisan('audit:prune', ['--days' => 365])->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recent->id]);
    }

    public function test_les_evenements_financiers_et_sensibles_sont_conserves_plus_longtemps(): void
    {
        $oldSensitive = $this->log('expense.created', now()->subDays(400));
        $oldTreatment = $this->log('treatment.created', now()->subDays(800));
        $veryOldSensitive = $this->log('sale.created', now()->subDays(2000));

        $this->artisan('audit:prune', ['--days' => 365, '--keep-days' => 1825])->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', ['id' => $oldSensitive->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $oldTreatment->id]);
        $this->assertDatabaseMissing('audit_logs', ['id' => $veryOldSensitive->id]);
    }

    public function test_le_mode_simulation_ne_supprime_rien(): void
    {
        $old = $this->log('auth.login', now()->subDays(400));

        $this->artisan('audit:prune', ['--days' => 365, '--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', ['id' => $old->id]);
    }

    public function test_la_purge_peut_etre_limitee_a_une_exploitation(): void
    {
        $farm = $this->createFarm();
        $otherFarm = $this->createFarm();

        $target = $this->log('auth.login', now()->subDays(400), $farm->id);
        $untouched = $this->log('auth.login', now()->subDays(400), $otherFarm->id);

        $this->artisan('audit:prune', ['--days' => 365, '--farm' => $farm->id])->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $untouched->id]);
    }
}
