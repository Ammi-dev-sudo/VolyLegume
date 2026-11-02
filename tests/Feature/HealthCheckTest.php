<?php

namespace Tests\Feature;

use App\Services\HealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    private ?string $backupDirectory = null;

    protected function tearDown(): void
    {
        if ($this->backupDirectory !== null) {
            File::deleteDirectory($this->backupDirectory);
        }

        parent::tearDown();
    }

    private function createBackupDirectory(?\DateTimeInterface $mtime = null): void
    {
        $base = storage_path('backups');
        File::ensureDirectoryExists($base);

        $this->backupDirectory = $base.'/test-'.uniqid();
        File::makeDirectory($this->backupDirectory);

        if ($mtime !== null) {
            touch($this->backupDirectory, $mtime->getTimestamp());
        }
    }

    public function test_l_endpoint_health_repond_avec_l_etat_des_services(): void
    {
        $this->createBackupDirectory(now()->subHour());

        $response = $this->getJson(route('health'));

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure([
                'status',
                'checked_at',
                'checks' => ['database', 'queue', 'backup', 'storage'],
            ]);
    }

    public function test_une_sauvegarde_ancienne_est_signalee_comme_degradee(): void
    {
        config()->set('health.backup_max_age_hours', 24);
        $this->createBackupDirectory(now()->subDays(10));

        $response = $this->getJson(route('health'));

        $response->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.backup.status', 'degraded');
    }

    public function test_l_acces_est_protege_quand_un_jeton_est_configure(): void
    {
        config()->set('health.token', 'secret-supervision');

        $this->getJson(route('health'))->assertNotFound();

        $this->getJson(route('health'), ['X-Health-Token' => 'secret-supervision'])
            ->assertOk();
    }

    public function test_l_endpoint_renvoie_503_quand_un_service_est_indisponible(): void
    {
        $this->mock(HealthService::class, function ($mock): void {
            $mock->shouldReceive('check')->once()->andReturn([
                'status' => 'down',
                'checked_at' => now()->toIso8601String(),
                'checks' => [
                    'database' => ['status' => 'down', 'message' => 'Base inaccessible.'],
                ],
            ]);
        });

        $this->getJson(route('health'))->assertStatus(503);
    }

    public function test_la_commande_system_health_s_execute(): void
    {
        $this->createBackupDirectory(now()->subHour());

        $this->artisan('system:health')->assertSuccessful();
        $this->artisan('system:health', ['--json' => true])->assertSuccessful();
    }
}
