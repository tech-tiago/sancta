<?php

namespace App\Http\Resources;

use App\Models\Plan;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Plano Sancta+. `monthly_equivalent_cents` e `savings_percent` são calculados no
 * servidor — use Plan::withPricing() na lista antes de serializar (senão a economia vem null).
 *
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $monthly = $this->monthlyEquivalentCents();

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'interval' => $this->interval->value,
            'interval_count' => $this->interval_count,
            'period_label' => $this->periodLabel(),
            'price_cents' => $this->price_cents,
            'price_formatted' => $this->formattedPrice(),
            'compare_at_price_cents' => $this->compare_at_price_cents,
            'compare_at_price_formatted' => $this->compare_at_price_cents ? Money::format($this->compare_at_price_cents) : null,
            'monthly_equivalent_cents' => $monthly,
            'monthly_equivalent_formatted' => $monthly ? Money::format($monthly) : null,
            'savings_percent' => $this->savingsPercent(),
            'trial_days' => $this->trial_days,
            'max_installments' => $this->max_installments,
            'badge' => $this->badge,
            'is_featured' => $this->is_featured,
            'features' => $this->features ?? [],
            'google_play' => [
                'product_id' => $this->google_play_product_id,
                'base_plan_id' => $this->google_play_base_plan_id,
            ],
        ];
    }
}
