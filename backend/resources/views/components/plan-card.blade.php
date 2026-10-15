@props(['plan', 'gift' => false])
@php
    // Cartão de plano: equivalente mensal e % de economia vêm do servidor (Plan::withPricing).
    $savings = $plan->savingsPercent();
    $monthly = $plan->months() > 1 ? $plan->monthlyEquivalentCents() : null;
    // O selo digitado no painel não pode contradizer a economia calculada no servidor.
    $flag = $plan->badge;
    if ($savings && $flag && \Illuminate\Support\Str::contains(mb_strtolower($flag), ['econom', '%'])) {
        $flag = null;
    }
    $flag = $flag ?: ($plan->is_featured ? 'Recomendado' : null);
    $headingId = 'plano-'.$plan->slug.($gift ? '-presente' : '');
@endphp
<article {{ $attributes->merge(['class' => 'plan'.($plan->is_featured ? ' featured' : '')]) }} aria-labelledby="{{ $headingId }}">
    @if ($flag)<span class="plan-flag">@if ($plan->is_featured)<x-sancta-icon name="star" filled />@endif{{ $flag }}</span>@endif
    <h3 class="plan-name" id="{{ $headingId }}">{{ $plan->name }}</h3>
    @if ($plan->description)<p class="plan-desc">{{ $plan->description }}</p>@endif
    <div class="plan-price"><strong>{{ $plan->formattedPrice() }}</strong> <small>{{ $plan->periodLabel() }}</small></div>
    <div class="plan-meta">
        @if ($plan->compare_at_price_cents)<span class="plan-compare">{{ \App\Support\Money::format($plan->compare_at_price_cents) }}</span>@endif
        @if ($monthly)<span>equivale a {{ \App\Support\Money::format($monthly) }}/mês</span>@endif
        @if ($savings)<span class="savings">Economize {{ $savings }}%</span>@endif
    </div>
    @if (! $gift && (array) $plan->features !== [])
        <ul class="checklist">
            @foreach ((array) $plan->features as $feature)
                <li><x-sancta-icon name="check" />{{ $feature }}</li>
            @endforeach
        </ul>
    @else
        <div class="mt-3"></div>
    @endif
    @if ($gift)
        <a class="btn btn-block {{ $plan->is_featured ? 'btn-primary' : 'btn-outline' }}" href="{{ route('checkout.show', ['plan' => $plan, 'gift' => 1]) }}"><x-sancta-icon name="gift" /> Presentear com o plano {{ $plan->name }}</a>
    @else
        <a class="btn btn-block {{ $plan->is_featured ? 'btn-primary' : 'btn-outline' }}" href="{{ route('checkout.show', $plan) }}">Assinar {{ $plan->name }}</a>
    @endif
    @if ($plan->max_installments > 1)<p class="plan-note">ou em até {{ $plan->max_installments }}x no cartão</p>@endif
</article>
