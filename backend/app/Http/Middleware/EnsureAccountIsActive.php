<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia contas suspensas/banidas imediatamente, mesmo com token ou sessão válidos.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->isActive() || $user->trashed())) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $user->currentAccessToken()?->delete();

                return response()->json(['message' => 'Conta suspensa. Fale com o suporte.'], 403);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Conta suspensa. Fale com o suporte.']);
        }

        return $next($request);
    }
}
