<?php

namespace App\Services\Ml;

/**
 * Contrat d'un constructeur de jeu de données ML.
 */
interface MlDatasetBuilder
{
    /**
     * @return list<string>
     */
    public function trainingHeader(): array;

    /**
     * @return list<string>
     */
    public function predictionHeader(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function trainingRows(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function predictionRows(): array;
}
