<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Génère un téléchargement CSV compatible avec les tableurs francophones.
 *
 * Le fichier est diffusé en flux pour ne pas charger de gros états en mémoire.
 * Le séparateur point-virgule et le BOM UTF-8 évitent les colonnes fusionnées
 * et les accents cassés à l'ouverture dans Excel / LibreOffice.
 */
class CsvExporter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, ';', '"', '\\');

            foreach ($rows as $row) {
                fputcsv($handle, array_map($this->sanitize(...), $row), ';', '"', '\\');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Neutralise les valeurs pouvant être interprétées comme des formules.
     */
    private function sanitize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
