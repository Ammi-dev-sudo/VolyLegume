<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'farm_id' => $this->farm_id,
            'production_unit_id' => $this->production_unit_id,
            'production_unit_code' => $this->whenLoaded('productionUnit', fn () => $this->productionUnit?->code),
            'crop_id' => $this->crop_id,
            'crop_name' => $this->whenLoaded('crop', fn () => $this->crop?->name),
            'start_date' => $this->start_date?->toDateString(),
            'reference_type' => $this->reference_type?->value,
            'expected_end_date' => $this->expected_end_date?->toDateString(),
            'actual_end_date' => $this->actual_end_date?->toDateString(),
            'cultivated_area_m2' => $this->cultivated_area_m2,
            'status' => $this->status?->value,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
