<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Support\AuditChain;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class AuditChainTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_les_entrees_d_audit_sont_chainees_par_hmac(): void
    {
        $farm = $this->createFarm();
        $user = $this->actingAsFarmMember($farm);
        $logger = app(AuditLogger::class);

        $logger->record('test.first', 'Première entrée.', null, ['n' => 1], $farm->id, $user->id);
        $logger->record('test.second', 'Deuxième entrée.', null, ['n' => 2], $farm->id, $user->id);

        [$first, $second] = [
            AuditLog::query()->where('action', 'test.first')->firstOrFail(),
            AuditLog::query()->where('action', 'test.second')->firstOrFail(),
        ];

        $this->assertNotNull($first->entry_hash);
        $this->assertSame($first->entry_hash, $second->prev_hash);
        $this->assertSame($first->entry_hash, AuditChain::hash($first->prev_hash, $first));
        $this->assertSame($second->entry_hash, AuditChain::hash($second->prev_hash, $second));

        $result = AuditChain::verify();

        $this->assertTrue($result['valid']);
        $this->assertSame(0, $result['legacy']);
        $this->assertGreaterThanOrEqual(2, $result['checked']);
    }

    public function test_une_alteration_du_journal_est_detectee(): void
    {
        $farm = $this->createFarm();
        $user = $this->actingAsFarmMember($farm);
        $logger = app(AuditLogger::class);

        $logger->record('test.before', 'Avant.', null, [], $farm->id, $user->id);
        $logger->record('test.middle', 'Au milieu.', null, [], $farm->id, $user->id);
        $logger->record('test.after', 'Après.', null, [], $farm->id, $user->id);

        $middle = AuditLog::query()->where('action', 'test.middle')->firstOrFail();

        DB::table('audit_logs')->where('id', $middle->id)->update(['description' => 'Description falsifiée.']);

        $result = AuditChain::verify();

        $this->assertFalse($result['valid']);
        $this->assertSame($middle->id, $result['first_broken_id']);
    }

    public function test_la_commande_de_verification_reussit_sur_une_chaine_intacte(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $this->artisan('audit:verify')->assertSuccessful();
    }

    public function test_la_commande_de_verification_echoue_sur_une_chaine_alteree(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $log = AuditLog::query()->latest('id')->firstOrFail();
        DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'action.falsifiee']);

        $this->artisan('audit:verify')->assertFailed();
    }

    public function test_la_commande_chain_les_entrees_historiques(): void
    {
        DB::table('audit_logs')->insert([
            ['action' => 'legacy.one', 'description' => 'Ancienne entrée 1.', 'created_at' => now()->subMinute()],
            ['action' => 'legacy.two', 'description' => 'Ancienne entrée 2.', 'created_at' => now()],
        ]);

        $this->assertSame(2, AuditLog::query()->whereNull('entry_hash')->count());

        $this->artisan('audit:chain')->assertSuccessful();

        $this->assertSame(0, AuditLog::query()->whereNull('entry_hash')->count());

        $result = AuditChain::verify();

        $this->assertTrue($result['valid']);
        $this->assertSame(2, $result['checked']);
        $this->assertSame(0, $result['legacy']);
    }

    public function test_la_commande_chain_refuse_une_chaine_existante(): void
    {
        $this->createFarm();

        $this->artisan('audit:chain')->assertFailed();
    }
}
