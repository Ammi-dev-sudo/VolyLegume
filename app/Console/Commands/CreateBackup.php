<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

class CreateBackup extends Command
{
    /**
     * @var string
     */
    protected $signature = 'backup:create
        {--path= : Répertoire de destination de la sauvegarde}
        {--database= : Connexion de base à sauvegarder}
        {--storage= : Répertoire des fichiers publics à archiver}';

    /**
     * @var string
     */
    protected $description = 'Crée une sauvegarde vérifiable de la base et des fichiers publics';

    public function handle(BackupService $backups): int
    {
        $connection = $this->option('database') ?: config('database.default');
        $path = $this->option('path') ?: storage_path('backups/'.now()->format('Ymd-His'));
        $storage = $this->option('storage') ?: storage_path('app/public');

        try {
            $result = $backups->create((string) $connection, (string) $path, (string) $storage);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $manifest = $result['manifest'];

        $this->info('Sauvegarde créée : '.$result['path']);
        $this->line('Base : '.$manifest['driver'].' ('.$manifest['database_file'].')');
        $this->line('Fichiers publics : '.$manifest['storage_files']);
        $this->line('Contrôle d’intégrité : manifest.json (SHA-256)');

        return self::SUCCESS;
    }
}
