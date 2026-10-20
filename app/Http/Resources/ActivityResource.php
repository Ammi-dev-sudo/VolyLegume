<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'farm_id' => $this->farm_id,
            'campaign_id' => $this->campaign_id,
            'calendar_step_id' => $this->calendar_step_id,
            'type' => $this->type?->value,
            'title' => $this->title,
            'description' => $this->description,
            'planned_date' => $this->planned_date?->toDateString(),
            'performed_at' => $this->performed_at?->toIso8601String(),
            'status' => $this->status?->value,
            'assigned_to' => $this->assigned_to,
            'created_by' => $this->created_by,
            'performed_by' => $this->performed_by,
            'estimated_cost' => $this->estimated_cost,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
