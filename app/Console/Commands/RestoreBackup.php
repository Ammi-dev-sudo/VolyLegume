<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

class RestoreBackup extends Command
{
    /**
     * @var string
     */
    protected $signature = 'backup:restore
        {path : Répertoire de la sauvegarde à restaurer}
        {--database= : Connexion de base à restaurer}
        {--storage= : Répertoire des fichiers publics à restaurer}
        {--force : Ne pas demander de confirmation}';

    /**
     * @var string
     */
    protected $description = 'Restaure et vérifie une sauvegarde de la base et des fichiers publics';

    public function handle(BackupService $backups): int
    {
        $path = (string) $this->argument('path');
        $connection = $this->option('database') ?: config('database.default');
        $storage = $this->option('storage') ?: storage_path('app/public');

        try {
            $manifest = $backups->readManifest($path);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Sauvegarde du '.$manifest['created_at'].' ('.$manifest['driver'].')');

        if (! $this->option('force') && ! $this->confirm('Cette opération remplace la base « '.$connection.' » et les fichiers de « '.$storage.' ». Continuer ?')) {
            $this->warn('Restauration annulée.');

            return self::FAILURE;
        }

        try {
            $backups->restore($path, (string) $connection, (string) $storage);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Restauration terminée et vérifiée.');
        $this->line('Base restaurée : '.$connection);
        $this->line('Fichiers publics restaurés : '.$manifest['storage_files']);
        $this->line('Pensez à vider les caches : php artisan optimize:clear');

        return self::SUCCESS;
    }
}
