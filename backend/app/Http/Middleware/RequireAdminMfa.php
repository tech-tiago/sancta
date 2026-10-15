<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Illuminate\Http\Request;

/**
 * 2FA obrigatório para a equipe, decidido a cada requisição (painel → Integrações e segurança).
 * O Filament só registra as rotas/middleware de 2FA obrigatório quando "isRequired" é verdadeiro
 * no carregamento das rotas — por isso o painel declara sempre "obrigatório" e a decisão real
 * fica aqui, o que também funciona com `route:cache` em produção.
 */
class RequireAdminMfa extends EnsureMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('sancta.admin.require_mfa')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
