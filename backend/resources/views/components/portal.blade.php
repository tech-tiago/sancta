@props(['mono' => false, 'title' => null])
{{-- Símbolo "Portal" (geometria oficial de brand/source/portal.js). O arco usa currentColor;
     a cruz é Ouro (ou a mesma cor do arco na versão mono). Decorativo por padrão. --}}
<svg {{ $attributes->merge(['class' => 'portal']) }} viewBox="0 0 486 512" @if($title) role="img" aria-label="{{ $title }}" @else aria-hidden="true" focusable="false" @endif>
    <path fill="currentColor" d="M0 512V243A243 243 0 0 1 486 243V512H354V228A111 111 0 0 0 132 228V512Z"/>
    <path fill="{{ $mono ? 'currentColor' : '#E3B65B' }}" d="M214 195L272 195L272 247L318 247L318 305L272 305L272 457L214 457L214 305L168 305L168 247L214 247Z"/>
</svg>
