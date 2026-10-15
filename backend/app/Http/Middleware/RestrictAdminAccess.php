<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe o painel administrativo a uma lista de IPs/CIDRs (SANCTA_ADMIN_IPS).
 * Fora da lista, responde 404 (não revela a existência do painel).
 * Também impede indexação por buscadores.
 */
class RestrictAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('sancta.admin.allowed_ips', []);

        if ($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            abort(404);
        }

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
