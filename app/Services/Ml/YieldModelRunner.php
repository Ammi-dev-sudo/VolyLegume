<?php

namespace App\Services\Ml;

/**
 * Exécute le script Python du modèle de rendement.
 */
class YieldModelRunner extends PythonModelRunner
{
    protected function script(): string
    {
        return 'ml/yield_model.py';
    }
}
