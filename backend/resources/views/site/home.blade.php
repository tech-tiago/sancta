@extends('layouts.site')

@php
    $premiumName = $settings->get('premium.name');
    $benefits = \App\Services\PremiumContent::normalizeBenefits($settings->get('premium.benefits'));
    $liveOn = $settings->bool('features.live');
@endphp

@section('content')
{{-- ───────── Herói: wordmark, slogan e o app em HTML/CSS ───────── --}}
<section class="hero on-blue" aria-labelledby="inicio-titulo">
    <div class="container hero-grid">
        <div class="hero-copy">
            <h1 class="hero-title" id="inicio-titulo"><img src="{{ asset('images/brand/sancta-wordmark-pergaminho.svg') }}" alt="{{ $settings->get('app.name') }}" width="420" height="100"></h1>
            <p class="hero-slogan">{{ $settings->get('app.slogan') }}</p>
            <p class="lead">Orações guiadas, liturgia diária, a Bíblia completa e igrejas perto de você. Um lugar para entrar e rezar, com calma, todos os dias.</p>
            <div class="hero-actions">
                <x-store-badge />
                <a class="btn btn-outline btn-lg" href="{{ route('bible.index') }}"><x-sancta-icon name="book" /> Ler a Bíblia online</a>
            </div>
            <ul class="hero-stats">
                <li><strong>{{ number_format($stats['books'], 0, ',', '.') }}</strong> livros da Bíblia</li>
                <li><strong>{{ number_format($stats['churches'], 0, ',', '.') }}</strong> igrejas no mapa</li>
                <li><strong>Grátis</strong> para começar</li>
            </ul>
        </div>
        <div class="hero-visual">
            <x-phone-today />
            <x-prayer-widget />
        </div>
    </div>
</section>

{{-- ───────── A ideia ───────── --}}
<section class="section" aria-labelledby="ideia">
    <div class="container split">
        <div>
            <span class="eyebrow"><x-portal class="icon" /> Por que Sancta</span>
            <h2 id="ideia">Uma porta aberta para rezar, todos os dias</h2>
            <p class="lead">Sancta é o lugar onde você entra para rezar. O nosso símbolo é a porta de um santuário, com a cruz como a luz lá dentro.</p>
            <p class="soft">Poucos toques entre você e Deus: o app sugere a oração do momento, lembra você no horário escolhido e acompanha a sua sequência de dias em oração — sem distrações.</p>
        </div>
        <div class="moments">
            <p class="list-label">Um dia com o Sancta</p>
            <ul class="today-list">
                <li class="today-item"><span class="arch arch-thumb"><x-sky sky="aurora" portrait /></span><div><strong>Manhã</strong><span class="sub">Oferecimento do dia e o Evangelho para meditar.</span></div></li>
                <li class="today-item"><span class="arch arch-thumb"><x-sky sky="luz" portrait /></span><div><strong>Durante o dia</strong><span class="sub">Liturgia, Angelus e um minuto de silêncio.</span></div></li>
                <li class="today-item"><span class="arch arch-thumb"><x-sky sky="entardecer" portrait /></span><div><strong>Fim da tarde</strong><span class="sub">Terço, novenas e jornadas no seu ritmo.</span></div></li>
                <li class="today-item"><span class="arch arch-thumb"><x-sky sky="noite" portrait /></span><div><strong>Noite</strong><span class="sub">Exame de consciência e oração para dormir em paz.</span></div></li>
            </ul>
        </div>
    </div>
</section>

{{-- ───────── Recursos ───────── --}}
<section class="section section-alt" aria-labelledby="recursos">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Tudo para a sua vida de oração</span>
            <h2 id="recursos">Um app católico completo, bonito e leve</h2>
            <p class="lead">Pensado para o dia a dia, do primeiro café à última oração da noite.</p>
        </div>
        <div class="grid grid-3 feature-grid">
            @foreach ([
                ['headphones', 'Orações guiadas', 'Terço, orações da manhã e da noite, exame de consciência e meditações — em texto e áudio.'],
                ['chalice', 'Liturgia diária', 'Leituras, salmo e Evangelho do dia, com a cor litúrgica e uma reflexão para viver a Palavra.'],
                ['book', 'Bíblia completa', '73 livros com busca, destaques, notas, marcadores e leitura em voz alta.'],
                ['flame', 'Jornadas e novenas', 'Novenas, planos de leitura e desafios de oração com progresso e lembretes.'],
                ['church', 'Igrejas perto de você', 'Mapa com horários de missa, confissão e adoração. A comunidade ajuda a manter tudo em dia.'],
                ['live', 'Ao vivo', 'Missas, terços e adoração transmitidos ao vivo, com aviso quando o seu canal começar.'],
            ] as [$featureIcon, $featureTitle, $featureText])
                <article class="card feature">
                    <div class="feature-icon"><x-sancta-icon :name="$featureIcon" /></div>
                    <h3>{{ $featureTitle }}</h3>
                    <p>{{ $featureText }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ───────── Ao vivo ───────── --}}
@if ($liveOn)
<section class="section" aria-labelledby="ao-vivo-chamada">
    <div class="container live-teaser">
        <div>
            <span class="live-badge"><span class="live-dot"></span>Ao vivo</span>
            <h2 id="ao-vivo-chamada" class="mt-2">Reze junto, onde estiver</h2>
            <p class="lead">Missas, terços e momentos de adoração transmitidos ao vivo pelos canais católicos, com a agenda do dia num só lugar.</p>
            <div class="row mt-2">
                <span class="chip"><x-sancta-icon name="chalice" /> Missas</span>
                <span class="chip"><x-sancta-icon name="orar" /> Terço</span>
                <span class="chip"><x-sancta-icon name="sun" /> Adoração</span>
            </div>
            <div class="actions mt-3">
                <a class="btn btn-primary" href="{{ route('live') }}">Ver transmissões de hoje <x-sancta-icon name="arrow-right" /></a>
            </div>
        </div>
        <div class="live-visual" aria-hidden="true">
            <div class="arch arch-tall"><x-sky sky="noite" portrait /></div>
            <span class="live-badge"><span class="live-dot"></span>Ao vivo agora</span>
        </div>
    </div>
</section>
@endif

{{-- ───────── Sancta+ ───────── --}}
<section class="section {{ $liveOn ? 'section-alt' : '' }}" id="planos" aria-labelledby="plus-titulo">
    <div class="container">
        <div class="band on-blue">
            <div class="premium-split">
                <div>
                    <x-seal class="seal-lg" />
                    <h2 id="plus-titulo" class="mt-2">{{ $settings->get('premium.headline') }}</h2>
                    <p class="lead">Desbloqueie todo o conteúdo e apoie a evangelização digital.</p>
                    <ul class="benefit-list">
                        @foreach (array_slice($benefits, 0, 5) as $benefit)
                            <li><span class="icon-tile"><x-sancta-icon :name="$benefit['icon']" /></span>{{ $benefit['title'] }}</li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    @if ($plans->isNotEmpty())
                        <div class="plan-rows">
                            @foreach ($plans as $plan)
                                <a class="plan-row {{ $plan->is_featured ? 'featured' : '' }}" href="{{ route('checkout.show', $plan) }}">
                                    <span>
                                        <strong>{{ $plan->name }}</strong>
                                        @if ($plan->savingsPercent())<span class="savings">Economize {{ $plan->savingsPercent() }}%</span>@elseif ($plan->badge)<span class="badge">{{ $plan->badge }}</span>@endif
                                        @if ($plan->months() > 1 && $plan->monthlyEquivalentCents())
                                            <span class="meta">equivale a {{ \App\Support\Money::format($plan->monthlyEquivalentCents()) }}/mês</span>
                                        @elseif ($plan->description)
                                            <span class="meta">{{ $plan->description }}</span>
                                        @endif
                                    </span>
                                    <span class="price"><strong>{{ $plan->formattedPrice() }}</strong><span>{{ $plan->periodLabel() }}</span></span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <div class="actions mt-3">
                        <a class="btn btn-light" href="{{ route('plans') }}">Conhecer o {{ $premiumName }}</a>
                        <a class="btn btn-outline" href="{{ route('gift.index') }}"><x-sancta-icon name="gift" /> Presentear</a>
                    </div>
                    <p class="small mt-2 mb-0" style="color:var(--nevoa)">Pague com Pix ou cartão em até 12x pelo site, ou assine no app pelo Google Play.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ───────── Bíblia e Igrejas ───────── --}}
<section class="section" aria-label="Bíblia e igrejas">
    <div class="container grid grid-2 duo-cards">
        <article class="card card-media">
            <div class="arch"><x-sky sky="aurora" /></div>
            <div class="card-body">
                <h3>Bíblia online, grátis</h3>
                <p class="soft">Leia todos os livros do Antigo e do Novo Testamento direto no navegador, com busca por palavras e leitura confortável.</p>
                <div class="actions mt-1"><a class="btn btn-outline" href="{{ route('bible.index') }}">Abrir a Bíblia <x-sancta-icon name="arrow-right" /></a></div>
            </div>
        </article>
        <article class="card card-media">
            <div class="arch"><x-sky sky="entardecer" /></div>
            <div class="card-body">
                <h3>Encontre uma igreja</h3>
                <p class="soft">Veja no mapa as igrejas, os horários de missa e os contatos. Sua paróquia não está lá? Cadastre em poucos minutos.</p>
                <div class="actions mt-1">
                    <a class="btn btn-outline" href="{{ route('churches.index') }}">Ver o mapa <x-sancta-icon name="arrow-right" /></a>
                    <a class="btn btn-ghost" href="{{ route('churches.create') }}"><x-sancta-icon name="plus" /> Cadastrar igreja</a>
                </div>
            </div>
        </article>
    </div>
</section>

@if ($testimonials->isNotEmpty())
<section class="section section-alt" aria-labelledby="depoimentos">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Comunidade</span>
            <h2 id="depoimentos">Quem reza com o Sancta</h2>
        </div>
        <div class="grid grid-3">
            @foreach ($testimonials as $t)
                <figure class="card quote-card">
                    <span class="stars" role="img" aria-label="Nota {{ $t->rating }} de 5">@for ($i = 0; $i < $t->rating; $i++)<x-sancta-icon name="star" filled />@endfor</span>
                    <blockquote>“{{ $t->content }}”</blockquote>
                    <figcaption>
                        <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($t->author, 0, 1)) }}</span>
                        <span><strong>{{ $t->author }}</strong>@if ($t->location){{ $t->location }}@endif</span>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

@if ($faqs->isNotEmpty())
<section class="section" aria-labelledby="duvidas">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Dúvidas</span>
            <h2 id="duvidas">Perguntas frequentes</h2>
        </div>
        <div class="faq">
            @foreach ($faqs as $faq)
                <details><summary>{{ $faq->question }}</summary><p>{{ $faq->answer }}</p></details>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="section" aria-labelledby="comece">
    <div class="container">
        <div class="band on-blue cta-final">
            <x-portal />
            <h2 id="comece">Comece hoje. É grátis.</h2>
            <p class="lead">Baixe o Sancta e transforme alguns minutos do seu dia em encontro com Deus.</p>
            <div class="actions mt-3"><x-store-badge /></div>
        </div>
    </div>
</section>
@endsection
