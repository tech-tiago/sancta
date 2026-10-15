<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'description', 'type', 'value', 'max_redemptions', 'max_redemptions_per_user', 'plan_ids', 'starts_at', 'expires_at', 'is_active',
    ];

    /** Por padrão, um uso por pessoa (null = sem limite por pessoa). Conferido em CheckoutService. */
    protected $attributes = [
        'max_redemptions_per_user' => 1,
    ];

    protected function casts(): array
    {
        return [
            'max_redemptions_per_user' => 'integer',
            'plan_ids' => 'array',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (Coupon $coupon) => $coupon->code = strtoupper(trim($coupon->code)));
    }

    public function isValidFor(Plan $plan): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->max_redemptions !== null && $this->redemptions_count >= $this->max_redemptions) {
            return false;
        }

        return empty($this->plan_ids) || in_array($plan->id, array_map('intval', $this->plan_ids), true);
    }

    /** Desconto em centavos — nunca deixa o valor final abaixo de R$ 1,00. */
    public function discountFor(int $priceCents): int
    {
        $discount = $this->type === 'percent'
            ? (int) floor($priceCents * min(100, max(0, $this->value)) / 100)
            : (int) $this->value;

        return max(0, min($discount, $priceCents - 100));
    }
}
