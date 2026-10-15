<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use Auditable, HasUuid;

    protected $fillable = [
        'plan_id', 'provider', 'status', 'provider_reference', 'auto_renew', 'starts_at', 'trial_ends_at',
        'ends_at', 'canceled_at', 'cancel_reason', 'notes', 'metadata',
    ];

    protected $hidden = ['purchase_token', 'purchase_token_hash'];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => SubscriptionStatus::class,
            'purchase_token' => 'encrypted',
            'auto_renew' => 'boolean',
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeEntitled(Builder $query): Builder
    {
        return $query->whereIn('status', SubscriptionStatus::entitled())
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function isEntitled(): bool
    {
        return in_array($this->status->value, SubscriptionStatus::entitled(), true)
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }
}
