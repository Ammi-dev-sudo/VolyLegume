<?php

namespace App\Services\Ml;

/**
 * Exécute le script Python du modèle de risque de retard d'activité.
 */
class ActivityRiskModelRunner extends PythonModelRunner
{
    protected function script(): string
    {
        return 'ml/activity_risk_model.py';
    }
}
