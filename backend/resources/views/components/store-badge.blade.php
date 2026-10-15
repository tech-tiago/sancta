@php($url = app(\App\Services\Settings::class)->get('app.play_store_url'))
<a {{ $attributes->merge(['class' => 'store-badge']) }} href="{{ $url }}" target="_blank" rel="noopener" aria-label="Baixar o Sancta no Google Play (abre em nova aba)">
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path fill="#34A853" d="M3.6 2.3 13.4 12l-9.8 9.7c-.4-.2-.6-.6-.6-1.1V3.4c0-.5.2-.9.6-1.1z"/>
        <path fill="#FBBC04" d="m16.8 15.3-3.4-3.3 3.4-3.3 3.8 2.2c1 .6 1 1.6 0 2.2z"/>
        <path fill="#4285F4" d="M16.8 15.3 13.4 12l-9.8 9.7c.3.2.8.2 1.3 0z"/>
        <path fill="#EA4335" d="M16.8 8.7 4.9 2.3c-.5-.3-1-.2-1.3 0l9.8 9.7z"/>
    </svg>
    <span><small>Disponível no</small><strong>Google Play</strong></span>
</a>
