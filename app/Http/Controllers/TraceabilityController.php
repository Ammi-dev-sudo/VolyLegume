<?php

namespace App\Http\Controllers;

use App\Models\ProduceLot;
use Illuminate\View\View;

class TraceabilityController extends Controller
{
    public function show(string $token): View
    {
        $lot = ProduceLot::query()
            ->with([
                'harvest.recordedBy',
                'campaign.crop',
                'campaign.productionUnit',
                'campaign.treatments.input',
                'campaign.activities',
            ])
            ->where('trace_token', $token)
            ->firstOrFail();

        return view('traceability.show', [
            'lot' => $lot,
        ]);
    }
}
