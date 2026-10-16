<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RestrictAdminAccess;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustProxies;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->prependToGroup('api', ForceJsonResponse::class);

        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'admin.ip' => RestrictAdminAccess::class,
        ]);

        // Proxies confiáveis (Cloudflare/balanceador): TRUSTED_PROXIES no .env, lido pela config.
        $middleware->replace(Illuminate\Http\Middleware\TrustProxies::class, TrustProxies::class);

        // Fora do ambiente local, só atende o domínio do APP_URL (e subdomínios): um cabeçalho Host
        // forjado não consegue influenciar links absolutos (canonical, retornos de pagamento etc.).
        $middleware->trustHosts();

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login'));
        $middleware->redirectUsersTo(fn () => route('account.show'));

        // Webhooks são autenticados por assinatura, não por CSRF.
        $middleware->validateCsrfTokens(except: ['webhooks/*', 'api/*']);

        $middleware->encryptCookies(except: ['sancta_theme']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Sessão expirada. Entre novamente.'], 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Não encontrado.'], 404);
            }
        });

        // Nunca registra senhas/tokens em logs de validação.
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'id_token', 'purchase_token']);
    })->create();
