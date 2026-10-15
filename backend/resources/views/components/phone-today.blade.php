@php
    // Mockup da tela "Hoje" do app, desenhado em HTML/CSS (não é captura de tela). Muda com o horário.
    $now = now('America/Sao_Paulo')->locale('pt_BR');
    $hour = (int) $now->format('G');
    $greeting = match (true) { $hour >= 5 && $hour < 12 => 'Bom dia', $hour >= 12 && $hour < 18 => 'Boa tarde', default => 'Boa noite' };
    $prayer = match (true) { $hour >= 5 && $hour < 12 => 'Oração da manhã', $hour >= 12 && $hour < 18 => 'Oração da tarde', default => 'Oração da noite' };
@endphp
<div {{ $attributes->merge(['class' => 'device']) }} role="img" aria-label="Tela Hoje do app Sancta: saudação, {{ mb_strtolower($prayer) }} e a lista Para hoje">
    <div class="device-screen" aria-hidden="true">
        <span class="ps-island"></span>
        <div class="ps-status"><span>9:41</span><span class="ps-sys"><span class="ps-bars"><i></i><i></i><i></i><i></i></span><span class="ps-battery"></span></span></div>
        <div class="ps-body">
            <div class="ps-appbar">
                <img class="theme-light-only" src="{{ asset('images/brand/sancta-wordmark-azul.svg') }}" alt="" width="84" height="20">
                <img class="theme-dark-only" src="{{ asset('images/brand/sancta-wordmark-pergaminho.svg') }}" alt="" width="84" height="20">
                <span class="ps-avatar">A</span>
            </div>
            <div class="ps-hello"><b>{{ $greeting }}, Ana</b><span>{{ \Illuminate\Support\Str::ucfirst($now->translatedFormat('l, j \d\e F')) }}</span></div>
            <div class="ps-feature">
                <div class="arch"><x-sky eager /></div>
                <div class="ps-feature-row">
                    <div><b>{{ $prayer }}</b><span>8 min, com o Evangelho do dia</span></div>
                    <span class="ps-play"><x-sancta-icon name="play-solid" /></span>
                </div>
            </div>
            <p class="ps-label">Para hoje</p>
            <div class="ps-rows">
                <div class="ps-row"><i><x-sancta-icon name="book" /></i><div><b>Evangelho do dia</b><span>Liturgia de hoje</span></div><x-sancta-icon name="chevron-right" /></div>
                <div class="ps-row"><i><x-sancta-icon name="calendar" /></i><div><b>Santo do dia</b><span>Conheça a história</span></div><x-sancta-icon name="chevron-right" /></div>
            </div>
        </div>
        <div class="ps-tabs">
            <span class="on"><x-sancta-icon name="portal" />Hoje</span>
            <span><x-sancta-icon name="orar" />Orar</span>
            <span><x-sancta-icon name="book" />Bíblia</span>
            <span><x-sancta-icon name="church" />Igrejas</span>
            <span><x-sancta-icon name="user" />Você</span>
        </div>
    </div>
</div>
