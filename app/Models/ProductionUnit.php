<?php

namespace App\Models;

use App\Enums\ProductionUnitStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Support\ParcelBoundary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionUnit extends Model
{
    use Auditable, BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'code',
        'name',
        'area_m2',
        'length_m',
        'width_m',
        'soil_type',
        'irrigation_type',
        'sun_exposure',
        'slope',
        'latitude',
        'longitude',
        'boundary',
        'location',
        'type',
        'status',
        'notes',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'area_m2' => 'decimal:2',
            'length_m' => 'decimal:2',
            'width_m' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'boundary' => 'array',
            'status' => ProductionUnitStatus::class,
        ];
    }

    /**
     * @return array{type: string, coordinates: array<int, array<int, array{0: float, 1: float}>>}|null
     */
    public function boundaryGeoJson(): ?array
    {
        return ParcelBoundary::normalize($this->boundary);
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    public function boundaryPoints(): array
    {
        return ParcelBoundary::points($this->boundaryGeoJson());
    }

    public function hasBoundary(): bool
    {
        return $this->boundaryPoints() !== [];
    }

    public function boundaryAreaM2(): ?float
    {
        $points = $this->boundaryPoints();

        return $points === [] ? null : round(ParcelBoundary::areaM2($points), 2);
    }

    public function boundaryPerimeterM(): ?float
    {
        $points = $this->boundaryPoints();

        return $points === [] ? null : round(ParcelBoundary::perimeterM($points), 2);
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function boundaryCentroid(): ?array
    {
        return ParcelBoundary::centroid($this->boundaryPoints());
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'production_unit_id');
    }

    public function irrigationEvents(): HasMany
    {
        return $this->hasMany(IrrigationEvent::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProductionUnitPhoto::class)->orderByDesc('taken_at')->orderByDesc('id');
    }
}
