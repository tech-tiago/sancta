@extends('layouts.site', ['title' => 'Planos '.$premium['name'], 'description' => 'Assine o Sancta+ com Pix ou cartão e desbloqueie orações guiadas, jornadas, reflexões e mais.'])

{{-- Conteúdo editável em Painel → Configurações → Sancta+ (PremiumContent). Preços, equivalente mensal e economia vêm do servidor. --}}
@php
    $benefits = $premium['benefit_items'];
    $free = array_values(array_filter([
        'Bíblia completa com busca',
        'Liturgia diária e Evangelho do dia',
        'Orações do dia a dia',
        'Igrejas e horários de missa',
        $settings->bool('features.live') ? 'Transmissões ao vivo' : null,
    ]));
@endphp

@section('content')
{{-- ───────── Herói ───────── --}}
<section class="hero on-blue" aria-labelledby="plus-titulo">
    <div class="container hero-grid hero-simple-grid">
        <div class="hero-copy">
            <x-seal class="seal-lg" :label="$premium['name']" />
            <h1 id="plus-titulo" class="mt-2">{{ $premium['headline'] }}</h1>
            @if ($premium['subheadline'])<p class="lead">{{ $premium['subheadline'] }}</p>@endif
            <div class="hero-actions">
                <a class="btn btn-light btn-lg" href="#planos">Ver os planos <x-sancta-icon name="arrow-right" /></a>
                <a class="btn btn-outline btn-lg" href="{{ route('gift.index') }}"><x-sancta-icon name="gift" /> Presentear</a>
            </div>
            @if ($premium['social_proof'] || $premium['testimonials'] !== [])
                <p class="social-proof mb-0">
                    @if ($premium['testimonials'] !== [])<span class="stars" role="img" aria-label="Avaliações de 5 estrelas">@for ($i = 0; $i < 5; $i++)<x-sancta-icon name="star" filled />@endfor</span>@endif
                    <span>{{ $premium['social_proof'] ?: 'Quem assina conta como o '.$premium['name'].' mudou a rotina de oração.' }}</span>
                </p>
            @endif
        </div>
        <div class="plus-hero-art" aria-hidden="true">
            <div class="arch arch-tall"><x-sky sky="aurora" portrait eager /></div>
            <span class="chip"><x-sancta-icon name="headphones" /> Orações guiadas</span>
            <span class="chip"><x-sancta-icon name="flame" /> Jornadas</span>
            <span class="chip"><x-sancta-icon name="sun" /> Reflexão diária</span>
        </div>
    </div>
</section>

{{-- ───────── Planos ───────── --}}
<section class="section" id="planos" aria-labelledby="planos-titulo">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Planos</span>
            <h2 id="planos-titulo">Escolha o seu plano</h2>
            <p class="lead">Pague com Pix ou cartão de crédito. O acesso é liberado na hora e vale no app e no site, com a mesma conta.</p>
        </div>
        @if ($plans->isEmpty())
            <p class="center muted">Nenhum plano disponível no momento. Você pode assinar pelo app.</p>
        @else
            <div class="plans">
                @foreach ($plans as $plan)
                    <x-plan-card :plan="$plan" />
                @endforeach
            </div>
        @endif

        <div class="trust-row">
            <div class="trust"><span class="icon-tile icon-tile-sm"><x-sancta-icon name="qr" /></span><div><strong>Pix na hora</strong><p>QR Code ou copia e cola, com liberação automática.</p></div></div>
            <div class="trust"><span class="icon-tile icon-tile-sm"><x-sancta-icon name="card" /></span><div><strong>Cartão em até 12x</strong><p>Ambiente seguro do Mercado Pago. Não guardamos dados do cartão.</p></div></div>
            <div class="trust"><span class="icon-tile icon-tile-sm"><x-sancta-icon name="shield" /></span><div><strong>Sem surpresas</strong><p>Planos do site não renovam sozinhos. Você decide quando renovar.</p></div></div>
        </div>
        @if ($premium['guarantee'])
            <p class="guarantee"><x-sancta-icon name="check-circle" /> <strong>{{ $premium['guarantee'] }}</strong></p>
        @endif
        <p class="center muted mt-2 mb-0">Quer dar de presente? <a href="{{ route('gift.index') }}">Presentear {{ $premium['name'] }}</a> · Ganhou um código? <a href="{{ route('gift.redeem') }}">Resgatar</a></p>
    </div>
</section>

{{-- ───────── Benefícios ───────── --}}
@if ($benefits !== [])
<section class="section section-alt" aria-labelledby="beneficios">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">O que você recebe</span>
            <h2 id="beneficios">Tudo para rezar com mais profundidade</h2>
        </div>
        <ul class="grid grid-3 plain-list">
            @foreach ($benefits as $benefit)
                <li class="card benefit-card">
                    <span class="icon-tile"><x-sancta-icon :name="$benefit['icon']" /></span>
                    <div>
                        <h3>{{ $benefit['title'] }}</h3>
                        @if ($benefit['description'])<p>{{ $benefit['description'] }}</p>@endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

{{-- ───────── Gratuito × Sancta+ ───────── --}}
<section class="section" aria-labelledby="comparar">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Comparar</span>
            <h2 id="comparar">Gratuito ou {{ $premium['name'] }}?</h2>
            <p class="lead">O essencial é grátis, para sempre. O {{ $premium['name'] }} abre todo o conteúdo e mantém o Sancta no ar.</p>
        </div>
        <div class="compare-wrap table-wrap">
            <table class="compare-table">
                <caption class="sr-only">Comparação entre o plano gratuito e o {{ $premium['name'] }}</caption>
                <thead>
                    <tr><th scope="col">Recurso</th><th scope="col">Gratuito</th><th scope="col" class="col-plus">{{ $premium['name'] }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($free as $item)
                        <tr>
                            <th scope="row">{{ $item }}</th>
                            <td class="yes"><x-sancta-icon name="check" /><span class="sr-only">Incluído</span></td>
                            <td class="yes col-plus"><x-sancta-icon name="check" /><span class="sr-only">Incluído</span></td>
                        </tr>
                    @endforeach
                    @foreach ($benefits as $benefit)
                        <tr>
                            <th scope="row">{{ $benefit['title'] }}</th>
                            <td class="no"><x-sancta-icon name="minus" /><span class="sr-only">Não incluído</span></td>
                            <td class="yes col-plus"><x-sancta-icon name="check" /><span class="sr-only">Incluído</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

{{-- ───────── Prova social ───────── --}}
@if ($premium['testimonials'] !== [])
<section class="section section-alt" aria-labelledby="depoimentos">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Depoimentos</span>
            <h2 id="depoimentos">Quem reza com o {{ $premium['name'] }}</h2>
        </div>
        <div class="grid grid-3">
            @foreach (array_slice($premium['testimonials'], 0, 6) as $t)
                <figure class="card quote-card">
                    <span class="stars" role="img" aria-label="Nota {{ $t['rating'] }} de 5">@for ($i = 0; $i < $t['rating']; $i++)<x-sancta-icon name="star" filled />@endfor</span>
                    <blockquote>“{{ $t['content'] }}”</blockquote>
                    <figcaption><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($t['author'], 0, 1)) }}</span><strong>{{ $t['author'] }}</strong></figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ───────── Perguntas frequentes ───────── --}}
@if ($premium['faq'] !== [] || $faqs->isNotEmpty())
<section class="section" aria-labelledby="perguntas">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Dúvidas</span>
            <h2 id="perguntas">Perguntas frequentes</h2>
        </div>
        <div class="faq">
            @foreach ($premium['faq'] as $item)
                <details><summary>{{ $item['question'] }}</summary><p>{{ $item['answer'] }}</p></details>
            @endforeach
            @foreach ($faqs as $faq)
                <details><summary>{{ $faq->question }}</summary><p>{{ $faq->answer }}</p></details>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ───────── Presentear ───────── --}}
<section class="section-last" aria-labelledby="presentear">
    <div class="container">
        <div class="band on-blue">
            <div class="premium-split">
                <div>
                    <span class="eyebrow"><x-sancta-icon name="gift" /> Presentear</span>
                    <h2 id="presentear">Dê de presente uma vida de oração</h2>
                    <p class="lead">Compre um plano e receba um código para enviar a quem você ama. Quem recebe resgata no site e usa no app.</p>
                    <div class="actions mt-3">
                        <a class="btn btn-light" href="{{ route('gift.index') }}">Presentear {{ $premium['name'] }}</a>
                        <a class="btn btn-outline" href="{{ route('gift.redeem') }}">Resgatar um presente</a>
                    </div>
                </div>
                <div class="gift-hero-art" aria-hidden="true">
                    <div class="giftcard">
                        <div class="giftcard-top"><img src="{{ asset('images/brand/sancta-wordmark-pergaminho.svg') }}" alt="" width="92" height="22" loading="lazy"><x-seal :label="$premium['name']" /></div>
                        <div><div class="giftcard-title">Um presente para você</div><div class="giftcard-code mt-1">SANC-••••-••••-••••</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
