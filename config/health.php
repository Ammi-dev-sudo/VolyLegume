<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seuils de supervision
    |--------------------------------------------------------------------------
    |
    | Ces valeurs alimentent l'endpoint /health et la commande system:health.
    | Adaptez-les à votre exploitation : une file d'attente chargée peut être
    | normale pendant une importation, mais pas au quotidien.
    |
    */

    'token' => env('HEALTH_TOKEN'),

    'backup_max_age_hours' => (int) env('HEALTH_BACKUP_MAX_AGE_HOURS', 48),

    'queue_backlog_warning' => (int) env('HEALTH_QUEUE_BACKLOG_WARNING', 1000),

    'failed_jobs_warning' => (int) env('HEALTH_FAILED_JOBS_WARNING', 1),

];
