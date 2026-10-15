@props(['message' => null])
{{-- Moldura das telas de acesso: arte em arco (céu do horário) no desktop e o formulário ao lado. --}}
<div class="auth">
    <div class="auth-art on-blue" aria-hidden="true">
        <div class="arch arch-tall"><x-sky portrait eager /></div>
        <p>{{ $message ?? app(\App\Services\Settings::class)->get('app.slogan') }}</p>
    </div>
    <div class="auth-main">
        <div class="auth-card">
            <x-portal class="auth-mark" />
            {{ $slot }}
        </div>
    </div>
</div>
