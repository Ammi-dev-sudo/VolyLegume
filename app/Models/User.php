<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\FarmRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'is_active', 'is_platform_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(FarmUser::class);
    }

    public function farms(): BelongsToMany
    {
        return $this->belongsToMany(Farm::class, 'farm_user')
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function createdActivities(): HasMany
    {
        return $this->hasMany(Activity::class, 'created_by');
    }

    public function managedFarms(): HasMany
    {
        return $this->hasMany(Farm::class, 'manager_id');
    }

    /**
     * Rôle de l'utilisateur dans une exploitation donnée (rattachement actif).
     */
    public function roleIn(Farm $farm): ?FarmRole
    {
        return $farm->roleOf($this);
    }

    /**
     * Exploitations pour lesquelles le compte possède un rattachement actif.
     */
    public function activeFarms()
    {
        return $this->farms()->wherePivot('is_active', true);
    }

    /**
     * Administrateur de la plateforme : il supervise tous les comptes et
     * toutes les exploitations, indépendamment d'un rattachement local.
     */
    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }
}
