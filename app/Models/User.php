<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const STAFF_ROLES = ['kasir', 'keuangan', 'administrator'];

    protected $fillable = [
        'owner_id',
        'name',
        'email',
        'password',
        'phone',
        'store_name',
        'store_address',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function cashiers(): HasMany
    {
        return $this->hasMany(User::class, 'owner_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function storeSetting(): HasOne
    {
        return $this->hasOne(StoreSetting::class);
    }

    public function isStoreOwner(): bool
    {
        return $this->role === 'owner' && blank($this->owner_id);
    }

    public function isStaff(): bool
    {
        return filled($this->owner_id) && $this->role !== 'developer';
    }

    public function isKasir(): bool
    {
        return $this->role === 'kasir';
    }

    public function isKeuangan(): bool
    {
        return $this->role === 'keuangan';
    }

    public function isAdministrator(): bool
    {
        return $this->role === 'administrator';
    }

    public function isDeveloper(): bool
    {
        return $this->role === 'developer';
    }

    public function canManageStaff(): bool
    {
        return $this->isStoreOwner() || $this->isAdministrator();
    }

    public function canAccessArea(string $area): bool
    {
        if ($this->isDeveloper()) {
            return false;
        }

        if ($this->isStoreOwner()) {
            return true;
        }

        return match ($area) {
            'dashboard' => true,
            'pos' => $this->isKasir() || $this->isAdministrator(),
            'inventory' => $this->isAdministrator(),
            'finance' => $this->isKeuangan() || $this->isAdministrator(),
            'reports' => $this->isKeuangan() || $this->isAdministrator(),
            'settings' => $this->isAdministrator(),
            'staff' => $this->isAdministrator(),
            'void' => $this->isKasir() || $this->isKeuangan() || $this->isAdministrator(),
            default => false,
        };
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'owner' => 'Pimpinan Toko',
            'administrator' => 'Administrator',
            'keuangan' => 'Keuangan',
            'kasir' => 'Kasir',
            'developer' => 'Developer',
            default => ucfirst((string) $this->role),
        };
    }

    /** ID pemilik toko (untuk scoping data). */
    public function storeOwnerId(): int
    {
        return (int) ($this->owner_id ?: $this->id);
    }

    public function storeOwner(): self
    {
        if ($this->owner_id) {
            return $this->owner ?? static::findOrFail($this->owner_id);
        }

        return $this;
    }

    public function activePlan(): ?SubscriptionPlan
    {
        $owner = $this->storeOwner();

        $subscription = $owner->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest()
            ->first();

        return $subscription?->plan;
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activePlan() !== null;
    }

    public function hasFeature(string $feature): bool
    {
        $plan = $this->activePlan();
        if (! $plan) {
            return false;
        }

        $flags = $plan->feature_flags ?? [];

        return (bool) ($flags[$feature] ?? false);
    }

    public function isPaidPlan(): bool
    {
        $plan = $this->activePlan();

        return $plan && ! $plan->is_free && (float) $plan->price > 0;
    }
}
