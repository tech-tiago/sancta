<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Enums\ThemePreference;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasUuid, InteractsWithAppAuthentication,
        InteractsWithAppAuthenticationRecovery, Notifiable, SoftDeletes;

    /**
     * Somente campos que o próprio usuário pode alterar.
     * role, status, google_id, email_verified_at etc. são definidos via forceFill
     * em código controlado — nunca a partir de input direto (mass assignment).
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'theme',
        'locale',
        'notification_preferences',
        'marketing_opt_in',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
        'last_login_ip',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    protected $attributes = [
        'role' => 'user',
        'status' => 'active',
        'theme' => 'system',
    ];

    private ?bool $premiumCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'theme' => ThemePreference::class,
            'notification_preferences' => 'array',
            'marketing_opt_in' => 'boolean',
            'terms_accepted_at' => 'datetime',
            'last_prayed_on' => 'date',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    // ───────────────────────── Relações ─────────────────────────

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function favoriteChurches(): BelongsToMany
    {
        return $this->belongsToMany(Church::class, 'church_favorites')->withPivot('notify', 'created_at');
    }

    public function favoritePrayers(): BelongsToMany
    {
        return $this->belongsToMany(Prayer::class, 'prayer_favorites')->withPivot('created_at');
    }

    public function bibleAnnotations(): HasMany
    {
        return $this->hasMany(BibleAnnotation::class);
    }

    public function readingProgress(): HasOne
    {
        return $this->hasOne(ReadingProgress::class);
    }

    public function prayerSessions(): HasMany
    {
        return $this->hasMany(PrayerSession::class);
    }

    public function journeyProgress(): HasMany
    {
        return $this->hasMany(JourneyProgress::class);
    }

    // ───────────────────────── Papéis e acesso ─────────────────────────

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function hasRoleAtLeast(UserRole $role): bool
    {
        return $this->role->atLeast($role);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaff() && $this->isActive() && ! $this->trashed();
    }

    // ───────────────────────── Assinatura (Sancta+) ─────────────────────────

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', SubscriptionStatus::entitled())
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->with('plan')
            ->orderByRaw('CASE WHEN ends_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('ends_at')
            ->first();
    }

    public function isPremium(): bool
    {
        return $this->premiumCache ??= $this->activeSubscription() !== null;
    }

    public function forgetPremiumCache(): void
    {
        $this->premiumCache = null;
    }

    // ───────────────────────── Apresentação ─────────────────────────

    public function avatarUrl(): ?string
    {
        if ($this->avatar_path) {
            return Storage::disk('public')->url($this->avatar_path);
        }

        return $this->avatar_url;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatarUrl();
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?: $this->name;
    }
}
