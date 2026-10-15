@extends('layouts.site', ['title' => 'Presentear '.$settings->get('premium.name'), 'description' => 'Dê o Sancta+ de presente: orações guiadas, jornadas e reflexões para quem você ama.'])

@php($premiumName = $settings->get('premium.name'))

@section('content')
<section class="hero on-blue" aria-labelledby="presente-titulo">
    <div class="container hero-grid hero-simple-grid">
        <div class="hero-copy">
            <span class="eyebrow"><x-sancta-icon name="gift" /> Presentear {{ $premiumName }}</span>
            <h1 id="presente-titulo">Dê de presente uma vida de oração</h1>
            <p class="lead">Escolha um plano e pague com Pix ou cartão. Você recebe um código de presente para enviar a quem quiser — o acesso vale no app e no site.</p>
            <div class="hero-actions">
                <a class="btn btn-light btn-lg" href="#escolha">Escolher o presente <x-sancta-icon name="arrow-right" /></a>
                <a class="btn btn-outline btn-lg" href="{{ route('gift.redeem') }}">Ganhei um código</a>
            </div>
        </div>
        <div class="gift-hero-art" aria-hidden="true">
            <div class="giftcard">
                <div class="giftcard-top"><img src="{{ asset('images/brand/sancta-wordmark-pergaminho.svg') }}" alt="" width="92" height="22"><x-seal :label="$premiumName" /></div>
                <div><div class="giftcard-title">Um presente para você</div><div class="giftcard-code mt-1">SANC-••••-••••-••••</div></div>
            </div>
        </div>
    </div>
</section>

<section class="section" aria-labelledby="como-funciona">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Simples assim</span>
            <h2 id="como-funciona">Como funciona</h2>
        </div>
        <ol class="steps">
            <li><h3>Escolha o plano</h3><p>O valor é o mesmo da assinatura. Planos do site não renovam sozinhos.</p></li>
            <li><h3>Receba o código</h3><p>Após a aprovação, mostramos o código uma vez e enviamos para o seu e-mail.</p></li>
            <li><h3>Quem recebe, resgata</h3><p>A pessoa entra no site, informa o código em <a href="{{ route('gift.redeem') }}">Resgatar presente</a> e o período é somado à conta dela.</p></li>
        </ol>
    </div>
</section>

<section class="section section-alt" id="escolha" aria-labelledby="planos-presente">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Planos</span>
            <h2 id="planos-presente">Escolha o presente</h2>
            <p class="lead">O código vale por 12 meses para resgate. A sua assinatura não muda.</p>
        </div>
        @if ($plans->isEmpty())
            <p class="muted center">Nenhum plano disponível no momento.</p>
        @else
            <div class="plans">
                @foreach ($plans as $plan)
                    <x-plan-card :plan="$plan" gift />
                @endforeach
            </div>
        @endif
        <p class="guarantee"><x-sancta-icon name="shield" /> Pagamento seguro pelo Mercado Pago · Ganhou um presente? <a href="{{ route('gift.redeem') }}">Resgate aqui</a></p>
    </div>
</section>
@endsection
