<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Services\CropCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarGenerationController extends Controller
{
    use UsesFarmContext;

    public function preview(Campaign $campaign, CropCalendarService $service): View
    {
        $campaign->load('crop');

        $existing = $campaign->activities()
            ->whereNotNull('calendar_step_id')
            ->get()
            ->keyBy('calendar_step_id');

        return view('calendar-generator.preview', [
            'campaign' => $campaign,
            'rows' => $service->preview($campaign),
            'existing' => $existing,
        ]);
    }

    public function generate(Request $request, Campaign $campaign, CropCalendarService $service): RedirectResponse
    {
        $validated = $request->validate([
            'step_ids' => ['required', 'array'],
            'step_ids.*' => ['integer', 'exists:calendar_steps,id'],
        ]);

        $result = $service->generate($campaign, $request->user(), $validated['step_ids']);

        return redirect()->route('campaigns.show', $campaign)->with('status', sprintf(
            'Calendrier généré : %d activité(s) créée(s), %d mise(s) à jour, %d conservée(s).',
            $result['created'],
            $result['updated'],
            $result['preserved'],
        ));
    }

    public function replan(Campaign $campaign, CropCalendarService $service): RedirectResponse
    {
        $count = $service->replan($campaign);

        return redirect()->route('campaigns.show', $campaign)->with('status', sprintf(
            '%d activité(s) planifiée(s) ont été replanifiées.',
            $count,
        ));
    }
}
