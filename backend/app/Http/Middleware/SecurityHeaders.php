<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança em todas as respostas:
 * CSP com nonce, anti-clickjacking, anti-MIME sniffing, HSTS (produção) e
 * política de permissões restritiva.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);
        Vite::useCspNonce($nonce);
        view()->share('cspNonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=(), usb=(), interest-cohort=()');
        $headers->remove('X-Powered-By');
        // O PHP (expose_php) emite o cabeçalho direto na SAPI, fora do objeto Response: remove lá também.
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        if ($request->isSecure() || app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // API: JSON puro, nunca deve ser renderizado como documento.
        if ($request->is('api/*')) {
            $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
            // O framework sempre preenche "no-cache, private": sem esta troca, o no-store nunca valia.
            // Só respostas marcadas como públicas de propósito (ex.: livros da Bíblia) mantêm o cache. Pentest fase 3 — SEC-19.
            if (! $headers->hasCacheControlDirective('public')) {
                $headers->set('Cache-Control', 'no-store, private');
            }

            return $response;
        }

        if (! $headers->has('Content-Security-Policy')) {
            $headerName = config('sancta.security.csp_report_only') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $headers->set($headerName, $this->csp($request, $nonce));
        }

        return $response;
    }

    private function csp(Request $request, string $nonce): string
    {
        $isAdmin = $request->is(config('sancta.admin.path').'*') || $request->is('livewire*');

        // O painel (Filament/Livewire/Alpine) precisa de 'unsafe-eval' e estilos inline.
        $script = $isAdmin
            ? "'self' 'unsafe-inline' 'unsafe-eval'"
            : "'self' 'nonce-{$nonce}'";

        return implode('; ', [
            "default-src 'self'",
            "script-src {$script}",
            // Fontes servidas localmente (Figtree em /fonts no site e no painel): nenhum host de fontes externo.
            "style-src 'self' 'unsafe-inline'",
            "font-src 'self' data:",
            "img-src 'self' data: blob: https:",
            "media-src 'self' https:",
            "connect-src 'self' https://*.tile.openstreetmap.org https://*.basemaps.cartocdn.com",
            "frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com https://www.mercadopago.com.br",
            "form-action 'self' https://www.mercadopago.com.br",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            'upgrade-insecure-requests',
        ]);
    }
}
