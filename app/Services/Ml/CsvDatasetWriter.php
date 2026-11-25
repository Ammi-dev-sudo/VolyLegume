<?php

namespace App\Services\Ml;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Écrit un jeu de données CSV (séparateur virgule, UTF-8, nombres en point).
 */
class CsvDatasetWriter
{
    /**
     * @param  list<string>  $header
     * @param  list<array<string, mixed>>  $rows
     */
    public function write(string $path, array $header, array $rows): int
    {
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Impossible d'écrire le jeu de données : {$path}");
        }

        fputcsv($handle, $header, ',', '"', '');

        foreach ($rows as $row) {
            $fields = array_map(
                fn (string $column): string => $this->format($row[$column] ?? null),
                $header,
            );

            fputcsv($handle, $fields, ',', '"', '');
        }

        fclose($handle);

        return count($rows);
    }

    private function format(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_float($value)) {
            return number_format($value, 6, '.', '');
        }

        return (string) $value;
    }
}
