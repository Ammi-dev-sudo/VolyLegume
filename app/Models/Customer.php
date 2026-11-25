<?php

namespace App\Models;

use App\Casts\EncryptedString;
use App\Enums\CustomerType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Support\BlindIndex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use Auditable, BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'code',
        'name',
        'type',
        'contact_name',
        'phone',
        'email',
        'address',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
            'is_active' => 'boolean',
            'contact_name' => EncryptedString::class,
            'phone' => EncryptedString::class,
            'email' => EncryptedString::class,
            'address' => EncryptedString::class,
            'notes' => EncryptedString::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Customer $customer): void {
            $customer->phone_index = self::blindIndex($customer->phone);
        });
    }

    /**
     * Index aveugle permettant une recherche exacte sur un champ chiffré.
     */
    public static function blindIndex(?string $value): ?string
    {
        return BlindIndex::for($value);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
