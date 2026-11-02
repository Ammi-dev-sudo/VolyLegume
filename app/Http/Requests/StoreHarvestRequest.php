<?php

namespace App\Http\Requests;

use App\Support\FarmContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation d'une récolte (Web, API et synchronisation hors ligne).
 */
class StoreHarvestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app(FarmContext::class)->farm() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $farmId = app(FarmContext::class)->id();

        return [
            'client_reference' => ['nullable', 'string', 'max:64'],
            'campaign_id' => ['required', Rule::exists('campaigns', 'id')->where('farm_id', $farmId)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'harvested_at' => ['required', 'date', 'before_or_equal:now'],
            'quality' => ['nullable', 'string', 'max:50'],
            'grade' => ['nullable', 'string', 'max:40'],
            'harvest_code' => ['nullable', 'string', 'max:60'],
            'harvested_area_m2' => ['nullable', 'numeric', 'gt:0'],
            'loss_quantity' => ['nullable', 'numeric', 'min:0', 'lte:quantity'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toHarvestData(): array
    {
        return $this->validated();
    }
}
