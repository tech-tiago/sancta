@extends('layouts.site', ['title' => (($gift ?? false) ? 'Presentear com o plano ' : 'Assinar ').$plan->name])

@php
    // Variáveis num bloco só, antes de qualquer diretiva curta de PHP (o Blade compila os blocos primeiro).
    $gift = $gift ?? false;
    $premiumName = app(\App\Services\Settings::class)->get('premium.name');
    $cardEnabled = in_array(\App\Enums\PaymentMethod::CreditCard, $methods, true);
@endphp

@section('content')
<section class="page-head">
    <div class="container container-md">
        @if ($gift)
            <a href="{{ route('gift.index') }}" class="back-link"><x-sancta-icon name="arrow-left" /> Presentear</a>
            <h1>Presentear com o plano {{ $plan->name }}</h1>
            <p class="lead">Depois da aprovação, você recebe um código de presente para enviar a quem quiser. A sua assinatura não muda.</p>
        @else
            <a href="{{ route('plans') }}" class="back-link"><x-sancta-icon name="arrow-left" /> Planos</a>
            <h1>Assinar o plano {{ $plan->name }}</h1>
            <p class="lead">{{ $plan->description }}</p>
        @endif
    </div>
</section>

<section class="section-tight section-last">
    <div class="container container-md checkout-grid">
        <aside class="card sub-card is-active on-blue checkout-summary" aria-labelledby="resumo">
            <x-seal :label="$premiumName" />
            <h2 class="card-title mt-2" id="resumo">{{ $gift ? 'Presente: ' : '' }}{{ $premiumName }} {{ $plan->name }}</h2>
            <div class="plan-price"><strong>{{ $plan->formattedPrice() }}</strong> <small>{{ $plan->periodLabel() }}</small></div>
            <div class="plan-meta">
                @if ($plan->months() > 1 && $plan->monthlyEquivalentCents())<span>equivale a {{ \App\Support\Money::format($plan->monthlyEquivalentCents()) }}/mês</span>@endif
                @if ($plan->max_installments > 1 && $cardEnabled)<span>em até {{ $plan->max_installments }}x no cartão</span>@endif
            </div>
            @if ((array) $plan->features !== [])
                <ul class="checklist mt-2">
                    @foreach ((array) $plan->features as $f)<li><x-sancta-icon name="check" />{{ $f }}</li>@endforeach
                </ul>
            @endif
            <hr>
            <p class="small mb-0">Conta: <strong>{{ auth()->user()->email }}</strong></p>
        </aside>

        <form class="card card-elevated form" method="post" action="{{ route('checkout.start', $plan) }}">
            @csrf
            @if ($gift)<input type="hidden" name="gift" value="1">@endif
            @if ($errors->any())
                <div class="alert alert-error" role="alert"><x-sancta-icon name="alert" /><div>{{ $errors->first() }}</div></div>
            @endif

            @if ($gift)
                <div class="alert alert-info"><x-sancta-icon name="gift" /><div>O código vale por 12 meses para resgate e é enviado para <strong>{{ auth()->user()->email }}</strong>.</div></div>
            @elseif ($activeSubscription)
                <div class="alert alert-info"><x-sancta-icon name="info" /><div>Você já tem acesso até <strong>{{ $activeSubscription->ends_at?->format('d/m/Y') ?? 'sem data de término' }}</strong>. Um novo pagamento estende o seu acesso a partir dessa data.</div></div>
            @endif

            @unless ($configured)
                <div class="alert alert-error" role="alert"><x-sancta-icon name="alert" /><div>Pagamentos pelo site estão temporariamente indisponíveis. Assine pelo app ou tente mais tarde.</div></div>
            @endunless

            <fieldset>
                <legend>Forma de pagamento</legend>
                @php($default = old('method', $methods[0]->value ?? null))
                <div class="choice-grid">
                    @foreach ($methods as $method)
                        @if ($method === \App\Enums\PaymentMethod::Pix)
                            <label class="choice"><input type="radio" name="method" value="pix" @checked($default === 'pix')> <x-sancta-icon name="qr" /><span>Pix<small>Aprovação na hora</small></span></label>
                        @else
                            <label class="choice"><input type="radio" name="method" value="credit_card" @checked($default === 'credit_card')> <x-sancta-icon name="card" /><span>Cartão de crédito<small>@if ($plan->max_installments > 1)Em até {{ $plan->max_installments }}x · @endif Mercado Pago</small></span></label>
                        @endif
                    @endforeach
                </div>
            </fieldset>

            <label for="coupon"><span>Cupom de desconto <span class="hint">(opcional)</span></span>
                <input id="coupon" type="text" name="coupon" value="{{ old('coupon') }}" maxlength="40" autocomplete="off" class="uppercase">
            </label>

            <label class="check"><input type="checkbox" name="accept_terms" value="1" required> <span>Concordo com os <a href="{{ route('pages.show', 'termos-de-uso') }}" target="_blank">Termos de uso</a>. Entendo que planos do site são pré-pagos e não renovam automaticamente.</span></label>

            <button class="btn btn-primary btn-lg btn-block" type="submit" @disabled(! $configured)><x-sancta-icon name="lock" /> Continuar para o pagamento</button>
            <p class="hint center mb-0"><x-sancta-icon name="shield" class="icon-inline" /> Pagamento processado pelo Mercado Pago. Não armazenamos dados do seu cartão.</p>
        </form>
    </div>
</section>
@endsection
