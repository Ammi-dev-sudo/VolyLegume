<?php

namespace App\Services\Ml;

/**
 * Contrat d'import d'un fichier de prédictions vers la table `predictions`.
 */
interface MlPredictionImporter
{
    /**
     * @param  array<string, mixed>  $modelMeta
     * @return int Nombre de prédictions importées.
     */
    public function import(string $csvPath, array $modelMeta = []): int;
}
