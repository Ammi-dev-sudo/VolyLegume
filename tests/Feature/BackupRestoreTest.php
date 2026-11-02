<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\ProduceLot;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    private string $tempPath;

    private string $backupPath;

    private string $storagePath;

    private string $originalConnection;

    private ?string $postgresDatabase = null;

    private ?string $sqliteDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        $this->tempPath = storage_path('framework/testing/backup-'.uniqid('', true));
        $this->backupPath = $this->tempPath.'/backup';
        $this->storagePath = $this->tempPath.'/storage';

        File::ensureDirectoryExists($this->backupPath);
        File::ensureDirectoryExists($this->storagePath.'/production-unit-photos');
        File::ensureDirectoryExists($this->storagePath.'/qr-codes');
        File::put($this->storagePath.'/production-unit-photos/parcelle.jpg', 'photo-content');
        File::put($this->storagePath.'/qr-codes/lot.png', 'qr-content');

        $original = config('database.connections.'.$this->originalConnection);
        $driver = $original['driver'] ?? null;

        if ($driver === 'pgsql') {
            $this->postgresDatabase = 'volylegume_backup_test_'.getmypid();

            DB::connection($this->originalConnection)->statement('DROP DATABASE IF EXISTS "'.$this->postgresDatabase.'" WITH (FORCE)');
            DB::connection($this->originalConnection)->statement('CREATE DATABASE "'.$this->postgresDatabase.'"');

            config(['database.connections.backup_test' => array_merge($original, [
                'database' => $this->postgresDatabase,
            ])]);
        } elseif ($driver === 'sqlite') {
            $this->sqliteDatabase = $this->tempPath.'/scratch.sqlite';
            touch($this->sqliteDatabase);

            config(['database.connections.backup_test' => [
                'driver' => 'sqlite',
                'database' => $this->sqliteDatabase,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]]);
        } else {
            $this->markTestSkipped('La sauvegarde est testée avec SQLite et PostgreSQL uniquement.');
        }

        DB::purge('backup_test');
        config(['database.default' => 'backup_test']);
        DB::setDefaultConnection('backup_test');

        Artisan::call('migrate', ['--database' => 'backup_test', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('backup_test');
        config(['database.default' => $this->originalConnection]);
        DB::setDefaultConnection($this->originalConnection);
        DB::purge($this->originalConnection);

        if ($this->postgresDatabase !== null) {
            DB::connection($this->originalConnection)
                ->statement('DROP DATABASE IF EXISTS "'.$this->postgresDatabase.'" WITH (FORCE)');
        }

        File::deleteDirectory($this->tempPath);

        parent::tearDown();
    }

    public function test_une_sauvegarde_restaure_la_base_et_les_fichiers(): void
    {
        $farm = Farm::factory()->create(['name' => 'Ferme sauvegarde']);
        $lot = ProduceLot::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'LOT-BACKUP',
        ]);

        $this->assertSame(0, $this->createBackup(), Artisan::output());
        $this->assertFileExists($this->backupPath.'/manifest.json');
        $this->assertFileExists($this->backupPath.'/storage.zip');
        $this->assertFileExists($this->backupPath.'/'.$this->databaseFile());

        $farm->update(['name' => 'Ferme modifiée']);
        $lot->delete();
        File::delete($this->storagePath.'/production-unit-photos/parcelle.jpg');

        $this->assertSame(0, $this->restoreBackup(), Artisan::output());

        $this->assertDatabaseHas('farms', ['id' => $farm->id, 'name' => 'Ferme sauvegarde'], 'backup_test');
        $this->assertDatabaseHas('produce_lots', ['id' => $lot->id, 'code' => 'LOT-BACKUP'], 'backup_test');
        $this->assertSame('photo-content', File::get($this->storagePath.'/production-unit-photos/parcelle.jpg'));
        $this->assertSame('qr-content', File::get($this->storagePath.'/qr-codes/lot.png'));
    }

    public function test_une_sauvegarde_alteree_est_refusee(): void
    {
        $this->assertSame(0, $this->createBackup(), Artisan::output());

        File::append($this->backupPath.'/storage.zip', 'corruption');

        $exitCode = $this->restoreBackup();
        $output = Artisan::output();

        $this->assertSame(1, $exitCode, $output);
        $this->assertStringContainsString('altérée', $output);
    }

    private function createBackup(): int
    {
        return Artisan::call('backup:create', [
            '--path' => $this->backupPath,
            '--database' => 'backup_test',
            '--storage' => $this->storagePath,
        ]);
    }

    private function restoreBackup(): int
    {
        return Artisan::call('backup:restore', [
            'path' => $this->backupPath,
            '--database' => 'backup_test',
            '--storage' => $this->storagePath,
            '--force' => true,
        ]);
    }

    private function databaseFile(): string
    {
        return config('database.connections.backup_test.driver') === 'pgsql'
            ? 'database.dump'
            : 'database.sqlite';
    }
}
