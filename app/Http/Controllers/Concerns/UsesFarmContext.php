<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Farm;
use App\Support\FarmContext;

trait UsesFarmContext
{
    protected function context(): FarmContext
    {
        return app(FarmContext::class);
    }

    protected function farm(): Farm
    {
        $farm = $this->context()->farm();

        abort_if($farm === null, 403, 'Aucune exploitation active.');

        return $farm;
    }

    protected function farmId(): int
    {
        return $this->farm()->id;
    }
}
