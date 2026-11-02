<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Sauvegarde et restauration vérifiables d'une base et des fichiers publics.
 *
 * Une sauvegarde contient le dump de la base (pg_dump pour PostgreSQL,
 * instantané VACUUM INTO pour SQLite), une archive ZIP des fichiers publics
 * (photos, QR codes, pièces des lots) et un manifeste JSON avec les empreintes
 * SHA-256 et les compteurs de lignes. La restauration contrôle ces empreintes
 * et ces compteurs avant de déclarer l'opération réussie.
 */
class BackupService
{
    private const FORMAT = 1;

    /**
     * @return array{path: string, manifest: array<string, mixed>}
     */
    public function create(string $connection, string $path, string $storagePath): array
    {
        $config = $this->connectionConfig($connection);
        $this->ensureDirectory($path);

        if (File::exists($path.'/manifest.json')) {
            throw new RuntimeException('Une sauvegarde existe déjà dans ce répertoire : '.$path);
        }

        $driver = (string) $config['driver'];
        $dumpName = $driver === 'pgsql' ? 'database.dump' : 'database.sqlite';
        $dumpPath = $path.'/'.$dumpName;

        if ($driver === 'pgsql') {
            $this->dumpPostgres($config, $dumpPath);
        } elseif ($driver === 'sqlite') {
            $this->snapshotSqlite($connection, $dumpPath);
        } else {
            throw new RuntimeException('Sauvegarde non prise en charge pour le pilote '.$driver.'.');
        }

        $storageArchive = $path.'/storage.zip';
        $storageFiles = $this->archiveStorage($storagePath, $storageArchive);

        $manifest = [
            'format' => self::FORMAT,
            'created_at' => now()->toIso8601String(),
            'connection' => $connection,
            'driver' => $driver,
            'database' => $config['database'] ?? null,
            'database_file' => $dumpName,
            'database_sha256' => (string) hash_file('sha256', $dumpPath),
            'storage_archive' => 'storage.zip',
            'storage_sha256' => (string) hash_file('sha256', $storageArchive),
            'storage_files' => $storageFiles,
            'tables' => $this->tableCounts($connection),
        ];

        File::put(
            $path.'/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
        );

        return ['path' => $path, 'manifest' => $manifest];
    }

    /**
     * @return array<string, mixed>
     */
    public function restore(string $path, string $connection, string $storagePath): array
    {
        $manifest = $this->readManifest($path);
        $this->verifyChecksums($path, $manifest);

        $config = $this->connectionConfig($connection);
        $dumpPath = $path.'/'.$manifest['database_file'];

        DB::purge($connection);

        if ($manifest['driver'] === 'pgsql') {
            $this->restorePostgres($config, $dumpPath);
        } elseif ($manifest['driver'] === 'sqlite') {
            $this->restoreSqlite($config, $dumpPath);
        } else {
            throw new RuntimeException('Restauration non prise en charge pour le pilote '.$manifest['driver'].'.');
        }

        $this->restoreStorage($path.'/'.$manifest['storage_archive'], $storagePath);

        $counts = $this->tableCounts($connection);
        if ($counts !== $manifest['tables']) {
            throw new RuntimeException('La base a été restaurée, mais ses compteurs de lignes diffèrent du manifeste (schéma différent ou écritures concurrentes).');
        }

        if ($this->countFiles($storagePath) !== $manifest['storage_files']) {
            throw new RuntimeException('Le nombre de fichiers restaurés ne correspond pas au manifeste.');
        }

        return $manifest;
    }

    /**
     * @return array<string, mixed>
     */
    public function readManifest(string $path): array
    {
        $manifestPath = $path.'/manifest.json';

        if (! is_file($manifestPath)) {
            throw new RuntimeException('Manifeste introuvable : '.$manifestPath);
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('Manifeste de sauvegarde invalide.');
        }

        $required = [
            'driver',
            'database_file',
            'database_sha256',
            'storage_archive',
            'storage_sha256',
            'storage_files',
            'tables',
        ];

        foreach ($required as $key) {
            if (! array_key_exists($key, $manifest)) {
                throw new RuntimeException('Manifeste de sauvegarde incomplet : '.$key.' manquant.');
            }
        }

        if (! in_array($manifest['driver'], ['pgsql', 'sqlite'], true) || ! is_array($manifest['tables'])) {
            throw new RuntimeException('Manifeste de sauvegarde incohérent.');
        }

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function verifyChecksums(string $path, array $manifest): void
    {
        $databasePath = $path.'/'.$manifest['database_file'];
        $archivePath = $path.'/'.$manifest['storage_archive'];

        if (! is_file($databasePath) || ! hash_equals($manifest['database_sha256'], (string) hash_file('sha256', $databasePath))) {
            throw new RuntimeException('La sauvegarde de la base est absente ou altérée.');
        }

        if (! is_file($archivePath) || ! hash_equals($manifest['storage_sha256'], (string) hash_file('sha256', $archivePath))) {
            throw new RuntimeException('L’archive des fichiers est absente ou altérée.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function dumpPostgres(array $config, string $target): void
    {
        $process = new Process([
            'pg_dump',
            '--format=custom',
            '--no-owner',
            '--no-privileges',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? '5432'),
            '--username='.($config['username'] ?? ''),
            '--dbname='.($config['database'] ?? ''),
            '--file='.$target,
        ], base_path(), ['PGPASSWORD' => (string) ($config['password'] ?? '')]);

        $process->setTimeout(600);

        try {
            $process->mustRun();
        } catch (Throwable $exception) {
            throw new RuntimeException('pg_dump a échoué : '.trim($process->getErrorOutput() ?: $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function restorePostgres(array $config, string $source): void
    {
        $process = new Process([
            'pg_restore',
            '--clean',
            '--if-exists',
            '--exit-on-error',
            '--no-owner',
            '--no-privileges',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? '5432'),
            '--username='.($config['username'] ?? ''),
            '--dbname='.($config['database'] ?? ''),
            $source,
        ], base_path(), ['PGPASSWORD' => (string) ($config['password'] ?? '')]);

        $process->setTimeout(600);

        try {
            $process->mustRun();
        } catch (Throwable $exception) {
            throw new RuntimeException('pg_restore a échoué : '.trim($process->getErrorOutput() ?: $exception->getMessage()), 0, $exception);
        }
    }

    private function snapshotSqlite(string $connection, string $target): void
    {
        if (File::exists($target)) {
            File::delete($target);
        }

        $escaped = str_replace("'", "''", str_replace('\\', '/', $target));

        DB::connection($connection)->statement("VACUUM INTO '{$escaped}'");
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function restoreSqlite(array $config, string $source): void
    {
        $target = (string) ($config['database'] ?? '');

        if ($target === '' || $target === ':memory:') {
            throw new RuntimeException('La restauration SQLite exige une base sur fichier.');
        }

        $this->ensureDirectory(dirname($target));

        foreach ([$target.'-wal', $target.'-shm'] as $sidecar) {
            if (File::exists($sidecar)) {
                File::delete($sidecar);
            }
        }

        if (! File::copy($source, $target)) {
            throw new RuntimeException('Impossible de restaurer le fichier SQLite.');
        }
    }

    private function archiveStorage(string $source, string $target): int
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('L’extension PHP zip est requise pour archiver les fichiers.');
        }

        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer l’archive des fichiers.');
        }

        $count = 0;

        if (is_dir($source)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $path = $file->getPathname();
                $relative = ltrim(str_replace($source, '', $path), DIRECTORY_SEPARATOR);
                $zip->addFile($path, $relative);
                $count++;
            }
        }

        $zip->close();

        return $count;
    }

    private function restoreStorage(string $archive, string $target): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('L’extension PHP zip est requise pour restaurer les fichiers.');
        }

        $zip = new ZipArchive;

        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Impossible d’ouvrir l’archive des fichiers.');
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\')) {
                $zip->close();

                throw new RuntimeException('L’archive des fichiers contient un chemin interdit.');
            }
        }

        if (is_dir($target)) {
            File::deleteDirectory($target);
        }

        $this->ensureDirectory($target);

        if (! $zip->extractTo($target)) {
            $zip->close();

            throw new RuntimeException('Impossible d’extraire l’archive des fichiers.');
        }

        $zip->close();
    }

    /**
     * @return array<string, int>
     */
    private function tableCounts(string $connection): array
    {
        $tables = collect(Schema::connection($connection)->getTables())
            ->pluck('name')
            ->sort()
            ->values();

        $counts = [];

        foreach ($tables as $table) {
            $counts[(string) $table] = DB::connection($connection)->table($table)->count();
        }

        ksort($counts);

        return $counts;
    }

    private function countFiles(string $path): int
    {
        return is_dir($path) ? count(File::allFiles($path)) : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfig(string $connection): array
    {
        $config = config('database.connections.'.$connection);

        if (! is_array($config)) {
            throw new RuntimeException('Connexion de base inconnue : '.$connection);
        }

        return $config;
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! File::makeDirectory($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException('Impossible de créer le répertoire : '.$path);
        }
    }
}
