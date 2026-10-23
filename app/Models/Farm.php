<?php

namespace App\Models;

use App\Enums\FarmRole;
use App\Enums\FarmStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\FarmFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Farm extends Model
{
    /** @use HasFactory<FarmFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'registration_number',
        'location',
        'address',
        'latitude',
        'longitude',
        'type',
        'area_m2',
        'manager_id',
        'contact_name',
        'contact_phone',
        'contact_email',
        'description',
        'status',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'area_m2' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => FarmStatus::class,
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(FarmUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'farm_user')
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function productionUnits(): HasMany
    {
        return $this->hasMany(ProductionUnit::class);
    }

    public function crops(): HasMany
    {
        return $this->hasMany(Crop::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function calendarSteps(): HasMany
    {
        return $this->hasMany(CalendarStep::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(Input::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(PhytosanitaryTreatment::class);
    }

    public function harvests(): HasMany
    {
        return $this->hasMany(Harvest::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function revenues(): HasMany
    {
        return $this->hasMany(Revenue::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function produceLots(): HasMany
    {
        return $this->hasMany(ProduceLot::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isActive(): bool
    {
        return $this->status === FarmStatus::Active;
    }

    public function roleOf(User $user): ?FarmRole
    {
        $membership = $this->memberships()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        return $membership?->role;
    }

    /**
     * Un compte ne peut accéder qu'aux exploitations où il possède un rattachement actif.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $farm = parent::resolveRouteBinding($value, $field);

        $user = request()->user();

        if ($farm !== null && ($user === null || $farm->roleOf($user) === null)) {
            throw (new ModelNotFoundException)->setModel(static::class, [$value]);
        }

        return $farm;
    }
}
