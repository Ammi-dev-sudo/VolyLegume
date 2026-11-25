<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Exécutable Python
    |--------------------------------------------------------------------------
    |
    | Les modèles sont entraînés et exécutés hors du cycle PHP. La commande
    | `ml:*` appelle ce binaire avec les scripts du dossier `ml/`.
    |
    */

    'python' => env('ML_PYTHON', 'python3'),

    'timeout' => (int) env('ML_TIMEOUT', 180),

    /*
    |--------------------------------------------------------------------------
    | Artefacts
    |--------------------------------------------------------------------------
    |
    | Jeux de données, modèles sérialisés et métriques sont écrits dans ce
    | dossier (ignoré par Git, comme le reste de storage/app).
    |
    */

    'storage_path' => storage_path('app/ml'),

];
