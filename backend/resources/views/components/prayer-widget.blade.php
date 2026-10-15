@php
    // Widget Android "Oração do dia" (mockup em HTML/CSS), como no material aprovado.
    $now = now('America/Sao_Paulo')->locale('pt_BR');
    $hour = (int) $now->format('G');
    $prayer = match (true) { $hour >= 5 && $hour < 12 => 'Oração da manhã', $hour >= 12 && $hour < 18 => 'Oração da tarde', default => 'Oração da noite' };
@endphp
<div {{ $attributes->merge(['class' => 'widget']) }} role="img" aria-label="Widget Oração do dia: {{ mb_strtolower($prayer) }}, 8 minutos">
    <div class="arch" aria-hidden="true"><x-sky portrait eager /></div>
    <div class="widget-info" aria-hidden="true">
        <small>{{ \Illuminate\Support\Str::ucfirst($now->translatedFormat('l, j \d\e F')) }}</small>
        <b>{{ $prayer }}</b>
        <div class="widget-foot"><span>8 min</span><span class="widget-play"><x-sancta-icon name="play-solid" /></span></div>
    </div>
</div>
