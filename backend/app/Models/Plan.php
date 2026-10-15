<?php

namespace App\Models;

use App\Enums\PlanInterval;
use App\Models\Concerns\Auditable;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Plan extends Model
{
    use Auditable, SoftDeletes;

    /**
     * Plano mensal usado como referência para "% de economia" (não é coluna do banco).
     * Preenchido por Plan::withPricing() com a lista de planos de UM canal (site ou app).
     */
    public ?Plan $pricingReference = null;

    protected $fillable = [
        'slug', 'name', 'description', 'interval', 'interval_count', 'price_cents', 'compare_at_price_cents',
        'currency', 'trial_days', 'max_installments', 'google_play_product_id', 'google_play_base_plan_id',
        'features', 'badge', 'is_featured', 'is_active', 'available_on_web', 'available_on_app', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'interval' => PlanInterval::class,
            'features' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'available_on_web' => 'boolean',
            'available_on_app' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function formattedPrice(): string
    {
        return Money::format($this->price_cents);
    }

    /** Duração total em meses (para cálculo de preço mensal equivalente). */
    public function months(): ?int
    {
        return match ($this->interval) {
            PlanInterval::Month => $this->interval_count,
            PlanInterval::Year => 12 * $this->interval_count,
            PlanInterval::Lifetime => null,
        };
    }

    public function monthlyEquivalentCents(): ?int
    {
        $months = $this->months();

        return $months ? (int) round($this->price_cents / $months) : null;
    }

    /** Plano mensal "cheio" (1 mês), base de comparação de economia. */
    public function isMonthly(): bool
    {
        return $this->interval === PlanInterval::Month && (int) $this->interval_count === 1;
    }

    /**
     * Define o plano mensal de referência de cada plano da lista. A lista deve conter
     * apenas planos ativos de um mesmo canal (site ou app); se houver mais de um plano
     * mensal, usa o mais barato — a economia anunciada nunca é exagerada.
     *
     * @param  Collection<int, Plan>  $plans
     * @return Collection<int, Plan>
     */
    public static function withPricing(Collection $plans): Collection
    {
        $reference = $plans
            ->filter(fn (Plan $plan) => $plan->isMonthly() && $plan->is_active && $plan->price_cents > 0)
            ->sortBy('price_cents')
            ->first();

        return $plans->each(fn (Plan $plan) => $plan->pricingReference = $reference);
    }

    /**
     * Economia (%) em relação a pagar o plano mensal de referência pelo mesmo período.
     * Arredonda para BAIXO (ex.: 24,7% → 24%). Null quando não há referência, para o próprio
     * plano mensal, planos vitalícios, moedas diferentes ou quando não há economia.
     */
    public function savingsPercent(): ?int
    {
        $reference = $this->pricingReference;
        $months = $this->months();

        $same = $reference === $this || ($this->exists && $reference?->is($this));
        if (! $reference || ! $months || $months <= 1 || $same || $reference->currency !== $this->currency) {
            return null;
        }

        $full = $reference->price_cents * $months;
        if ($full <= 0 || $this->price_cents >= $full) {
            return null;
        }

        $percent = intdiv(($full - $this->price_cents) * 100, $full);

        return $percent > 0 ? $percent : null;
    }

    public function periodLabel(): string
    {
        return match ($this->interval) {
            PlanInterval::Month => $this->interval_count === 1 ? 'por mês' : "a cada {$this->interval_count} meses",
            PlanInterval::Year => $this->interval_count === 1 ? 'por ano' : "a cada {$this->interval_count} anos",
            PlanInterval::Lifetime => 'pagamento único',
        };
    }

    /** Data de término de um período iniciado em $from (null = vitalício). */
    public function periodEndFrom(CarbonInterface $from): ?CarbonInterface
    {
        return match ($this->interval) {
            PlanInterval::Month => $from->copy()->addMonthsNoOverflow($this->interval_count),
            PlanInterval::Year => $from->copy()->addYearsNoOverflow($this->interval_count),
            PlanInterval::Lifetime => null,
        };
    }
}
