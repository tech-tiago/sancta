@props(['sky' => null, 'portrait' => false, 'eager' => false])
@php
    // Céus da marca (MARCA.md §8): Aurora 05–11h59 · Luz 12–15h59 · Entardecer 16–18h59 · Noite 19–04h59.
    $hour = (int) now('America/Sao_Paulo')->format('G');
    $sky ??= match (true) {
        $hour >= 5 && $hour < 12 => 'aurora',
        $hour >= 12 && $hour < 16 => 'luz',
        $hour >= 16 && $hour < 19 => 'entardecer',
        default => 'noite',
    };
    $sky = in_array($sky, ['aurora', 'luz', 'entardecer', 'noite'], true) ? $sky : 'aurora';
@endphp
<img {{ $attributes->merge(['class' => 'sky']) }} src="{{ asset('images/illustrations/'.$sky.($portrait ? '-retrato' : '').'.svg') }}" alt="" width="{{ $portrait ? 600 : 1200 }}" height="800" @unless($eager) loading="lazy" @endunless decoding="async">
