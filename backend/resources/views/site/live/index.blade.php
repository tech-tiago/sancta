@extends('layouts.site', ['title' => 'Ao vivo', 'description' => 'Missas, terços e momentos de oração ao vivo, com a agenda de hoje dos canais católicos.'])

@php
    $when = function ($date) use ($now) {
        if (! $date) {
            return null;
        }
        $date = \Carbon\CarbonImmutable::instance($date)->setTimezone(\App\Models\LiveChannel::TIMEZONE)->locale('pt_BR');

        return match (true) {
            $date->isSameDay($now) => 'hoje às '.$date->format('H:i'),
            $date->isSameDay($now->addDay()) => 'amanhã às '.$date->format('H:i'),
            default => $date->translatedFormat('l').', '.$date->format('H:i'),
        };
    };
    $appName = app(\App\Services\Settings::class)->get('app.name');
@endphp

@section('content')
<section class="hero on-blue" aria-labelledby="ao-vivo-titulo">
    <div class="container hero-grid hero-simple-grid">
        <div class="hero-copy">
            <span class="live-badge"><span class="live-dot"></span>Ao vivo</span>
            <h1 id="ao-vivo-titulo" class="mt-2">Reze junto, ao vivo</h1>
            <p class="lead">Missas, terços e momentos de oração transmitidos agora e ao longo do dia. As transmissões abrem no YouTube.</p>
            <div class="hero-actions">
                <a class="btn btn-light" href="#agenda-hoje"><x-sancta-icon name="calendar" /> Agenda de hoje</a>
                <a class="btn btn-outline" href="#canais">Ver canais</a>
            </div>
        </div>
        <div class="plus-hero-art hide-sm" aria-hidden="true">
            <div class="arch arch-tall"><x-sky sky="noite" portrait eager /></div>
            <span class="chip"><x-sancta-icon name="chalice" /> Santa Missa</span>
            <span class="chip"><x-sancta-icon name="orar" /> Terço</span>
            <span class="chip"><x-sancta-icon name="sun" /> Adoração</span>
        </div>
    </div>
</section>

<section class="section" aria-labelledby="ao-vivo-agora">
    <div class="container">
        <div class="section-head section-head-left">
            <h2 id="ao-vivo-agora">Ao vivo agora</h2>
        </div>
        @if ($liveNow->isEmpty())
            <div class="card center">
                <span class="status-icon wait mx-auto"><x-sancta-icon name="live" /></span>
                <p class="mb-0 soft">Nenhuma transmissão neste momento. Confira a agenda de hoje logo abaixo.</p>
            </div>
        @else
            <div class="live-grid">
                @foreach ($liveNow as $channel)
                    @php($title = $channel->isLive() ? $channel->live_title : $channel->currentSchedule($now)?->title)
                    <article class="card live-card">
                        <div @class(['live-thumb', 'live-thumb-empty' => ! $channel->thumbnailUrl()])>
                            @if ($channel->thumbnailUrl())
                                <img src="{{ $channel->thumbnailUrl() }}" alt="" width="480" height="270" loading="lazy">
                            @else
                                <x-portal />
                            @endif
                            @if ($channel->isLive())
                                <span class="live-badge"><span class="live-dot"></span>Ao vivo</span>
                            @else
                                <span class="badge badge-muted">No horário da transmissão</span>
                            @endif
                        </div>
                        <div class="live-body">
                            <p class="meta">{{ $channel->categoryLabel() }}</p>
                            <h3>{{ $channel->name }}</h3>
                            @if ($title)<p class="soft mb-0">{{ $title }}</p>@endif
                            <a class="btn btn-primary btn-block" href="{{ $channel->watchUrl() }}" target="_blank" rel="noopener"><x-sancta-icon name="play" /> Assistir no YouTube</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>

<section class="section section-alt" id="agenda-hoje" aria-labelledby="agenda-titulo">
    <div class="container container-md">
        <div class="section-head section-head-left">
            <h2 id="agenda-titulo">Agenda de hoje</h2>
            <p class="lead mb-0">Horários de Brasília. Toque em uma transmissão para abrir no YouTube.</p>
        </div>
        @if ($today === [])
            <p class="muted mb-0">Nenhum horário cadastrado para hoje.</p>
        @else
            <ol class="agenda">
                @foreach ($today as $item)
                    <li @class(['agenda-item', 'is-now' => $item['is_now'], 'is-past' => $item['is_past']])>
                        <time datetime="{{ $item['starts_at']->toIso8601String() }}">{{ $item['starts_at']->format('H:i') }}</time>
                        <div>
                            <strong>{{ $item['schedule']->title ?: $item['channel']->categoryLabel() }}</strong>
                            <span class="sub">{{ $item['channel']->name }}</span>
                        </div>
                        <span class="state">
                            @if ($item['is_now'])
                                <a class="btn btn-primary btn-sm" href="{{ $item['channel']->watchUrl() }}" target="_blank" rel="noopener"><span class="live-dot" aria-hidden="true"></span> Agora — assistir</a>
                            @elseif ($item['is_past'])
                                <span class="badge badge-muted">Encerrada</span>
                            @else
                                <span class="badge">Em breve</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</section>

<section class="section" id="canais" aria-labelledby="canais-titulo">
    <div class="container">
        <div class="section-head section-head-left">
            <h2 id="canais-titulo">Canais</h2>
        </div>
        @if ($channels->isEmpty())
            <p class="muted mb-0">Nenhum canal disponível no momento.</p>
        @else
            <div class="live-grid">
                @foreach ($channels as $channel)
                    <article class="card live-body channel-card">
                        <div class="channel-head">
                            @if ($channel->logoUrl())
                                <img class="channel-logo" src="{{ $channel->logoUrl() }}" alt="" width="56" height="56" loading="lazy">
                            @else
                                <span class="channel-logo channel-logo-empty"><x-portal /></span>
                            @endif
                            <div>
                                <h3>{{ $channel->name }}</h3>
                                <p class="meta">{{ $channel->categoryLabel() }}</p>
                            </div>
                        </div>
                        @if ($channel->description)<p class="soft mt-1 mb-0">{{ $channel->description }}</p>@endif
                        <p class="mt-1 mb-0">
                            @if ($channel->isLive())
                                <span class="live-badge"><span class="live-dot"></span>Ao vivo</span>
                            @elseif ($channel->isUpcoming())
                                <span class="badge"><x-sancta-icon name="clock" /> Agendada: {{ $when($channel->live_scheduled_at) }}</span>
                            @elseif ($next = $channel->nextScheduledAt($now))
                                <span class="badge badge-muted"><x-sancta-icon name="clock" /> Próxima: {{ $when($next) }}</span>
                            @endif
                        </p>
                        <a class="btn btn-outline btn-block" href="{{ $channel->watchUrl() }}" target="_blank" rel="noopener"><x-sancta-icon name="external" /> Abrir no YouTube</a>
                    </article>
                @endforeach
            </div>
        @endif
        <div class="card app-cta mt-4">
            <div><strong>Receba um aviso quando o seu canal entrar ao vivo</strong><p>No app {{ $appName }}, toque em "Me avise" no canal que você acompanha.</p></div>
            <x-store-badge />
        </div>
    </div>
</section>
@endsection
