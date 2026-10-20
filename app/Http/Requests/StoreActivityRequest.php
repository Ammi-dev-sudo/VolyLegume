<?php

namespace App\Http\Requests;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Support\FarmContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation d'une activité (Web et API).
 *
 * L'exploitation est toujours celle du contexte serveur ; aucune référence
 * étrangère n'est acceptée.
 */
class StoreActivityRequest extends FormRequest
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
            'campaign_id' => ['required', Rule::exists('campaigns', 'id')->where('farm_id', $farmId)],
            'calendar_step_id' => ['nullable', Rule::exists('calendar_steps', 'id')->where('farm_id', $farmId)],
            'type' => ['required', Rule::enum(ActivityType::class)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'planned_date' => ['required', 'date'],
            'status' => ['required', Rule::enum(ActivityStatus::class)],
            'performed_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', Rule::exists('farm_user', 'user_id')->where('farm_id', $farmId)->where('is_active', true)],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Attributs prêts pour la persistance : réalisation servie par le compte
     * connecté lorsque l'activité est déclarée réalisée.
     *
     * @return array<string, mixed>
     */
    public function toActivityAttributes(): array
    {
        $validated = $this->validated();

        if (($validated['status'] ?? null) === ActivityStatus::Completed->value) {
            $validated['performed_at'] = $this->date('performed_at') ?? now();
            $validated['performed_by'] = $this->user()->id;
        }

        return $validated;
    }
}
