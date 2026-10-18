<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'business_name',
        'email',
        'phone',
        'address',
        'city',
        'status',
        'currency',
        'timezone',
        'logo_path',
        'settings',
        'trial_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Suscripción activa más reciente del comercio.
     *
     * @return HasOne<Subscription, $this>
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latestOfMany();
    }

    /**
     * Indica si el comercio tiene una suscripción vigente: estado "active" y sin
     * vencimiento o con vencimiento posterior al momento actual en Honduras.
     *
     * Las suscripciones se activan SIEMPRE de forma manual por el propietario;
     * este método nunca cambia estados, solo consulta el acceso al panel.
     */
    public function hasActiveSubscription(): bool
    {
        $now = CarbonImmutable::now('America/Tegucigalpa');

        return $this->subscriptions()
            ->where('status', 'active')
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->exists();
    }

    /**
     * Indica si el comercio está dentro de un período de prueba vigente: estado
     * "trial" y con fecha de fin de prueba posterior al momento actual.
     *
     * El propietario define manualmente la prueba; esto solo consulta el acceso.
     */
    public function hasValidTrial(): bool
    {
        return $this->status === 'trial'
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->gte(CarbonImmutable::now('America/Tegucigalpa'));
    }

    /**
     * Indica si el comercio puede usar el panel: suscripción vigente O prueba
     * vigente. Ninguna de las dos se activa de forma automática por el cliente.
     */
    public function hasActiveAccess(): bool
    {
        return $this->hasActiveSubscription() || $this->hasValidTrial();
    }
}
