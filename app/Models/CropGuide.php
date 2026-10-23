<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CropGuide extends Model
{
    use HasFactory;

    protected $fillable = [
        'crop_id',
        'overview',
        'varieties',
        'climate',
        'soil',
        'land_preparation',
        'planting_material',
        'nursery',
        'sowing',
        'transplanting',
        'irrigation',
        'fertilization',
        'maintenance',
        'pests_diseases',
        'cycle_duration',
        'harvest',
        'expected_yield',
        'storage',
        'sources',
        'common_steps',
        'category_steps',
        'specific_steps',
    ];

    protected function casts(): array
    {
        return [
            'common_steps' => 'array',
            'category_steps' => 'array',
            'specific_steps' => 'array',
        ];
    }

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }
}
