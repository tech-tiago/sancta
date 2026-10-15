@props(['label' => null])
{{-- Selo Sancta+ (Ouro com texto Tinta: 9,05:1). Ouro só em destaques pequenos. --}}
<span {{ $attributes->merge(['class' => 'seal']) }}><x-portal mono class="seal-portal" />{{ $label ?? app(\App\Services\Settings::class)->get('premium.name') }}</span>
