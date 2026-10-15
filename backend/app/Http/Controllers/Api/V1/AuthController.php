<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Account\AccountService;
use App\Services\Google\GoogleIdTokenVerifier;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Autenticação do app (tokens Sanctum por dispositivo, com expiração).
 * Mensagens genéricas evitam enumeração de contas; tentativas são limitadas.
 */
class AuthController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
            'password' => ['required', 'string', PasswordRule::defaults()],
            'device_name' => ['required', 'string', 'max:100'],
            'accept_terms' => ['accepted'],
            'marketing_opt_in' => ['sometimes', 'boolean'],
        ]);

        $email = mb_strtolower(trim($data['email']));

        if (User::withTrashed()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Não foi possível criar a conta com este e-mail. Se já possui cadastro, entre ou recupere a senha.']);
        }

        $user = User::query()->create([
            'name' => strip_tags(trim($data['name'])),
            'email' => $email,
            'password' => $data['password'],
            'marketing_opt_in' => (bool) ($data['marketing_opt_in'] ?? false),
        ]);
        $user->forceFill(['terms_accepted_at' => now(), 'source' => 'app', 'password_changed_at' => now()])->save();

        event(new Registered($user));

        return $this->tokenResponse($user, $data['device_name'], $request, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'string', 'max:200'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $key = 'login:'.sha1($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Muitas tentativas. Tente novamente em '.RateLimiter::availableIn($key).' segundos.',
            ])->status(429);
        }

        $user = User::query()->where('email', $email)->first();

        // Hash::check é executado mesmo sem usuário (tempo constante — dificulta enumeração).
        $valid = Hash::check($data['password'], $user?->password ?? '$2y$12$'.str_repeat('a', 53));

        if (! $user || ! $user->password || ! $valid) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => 'Esta conta está suspensa. Fale com o suporte.'])->status(403);
        }

        RateLimiter::clear($key);

        return $this->tokenResponse($user, $data['device_name'], $request);
    }

    public function google(Request $request, GoogleIdTokenVerifier $verifier): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string', 'max:4096'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $profile = $verifier->verify($data['id_token']);
        if (! $profile) {
            throw ValidationException::withMessages(['id_token' => 'Não foi possível validar o login com o Google.'])->status(401);
        }

        $user = User::query()->where('google_id', $profile['sub'])->first()
            ?? User::query()->where('email', $profile['email'])->first();

        if ($user && ! $user->isActive()) {
            throw ValidationException::withMessages(['id_token' => 'Esta conta está suspensa. Fale com o suporte.'])->status(403);
        }

        $status = 200;
        if (! $user) {
            if (User::onlyTrashed()->where('email', $profile['email'])->exists()) {
                throw ValidationException::withMessages(['id_token' => 'Esta conta foi excluída. Fale com o suporte.'])->status(403);
            }

            $user = User::query()->create([
                'name' => Str::limit($profile['name'] ?: Str::before($profile['email'], '@'), 120, ''),
                'email' => $profile['email'],
            ]);
            $user->forceFill(['terms_accepted_at' => now(), 'source' => 'app']);
            $status = 201;
        }

        // O Google já verificou o e-mail: vincula a conta (inclusive contas antigas por e-mail).
        $user->forceFill([
            'google_id' => $profile['sub'],
            'email_verified_at' => $user->email_verified_at ?? now(),
            'avatar_url' => $profile['picture'] ?? $user->avatar_url,
        ])->save();

        return $this->tokenResponse($user, $data['device_name'], $request, $status);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Todas as sessões foram encerradas.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:191']]);

        Password::sendResetLink(['email' => mb_strtolower(trim($data['email']))]);

        // Resposta idêntica exista ou não a conta (anti-enumeração).
        return response()->json(['message' => 'Se o e-mail estiver cadastrado, você receberá um link para redefinir a senha.']);
    }

    private function tokenResponse(User $user, string $deviceName, Request $request, int $status = 200): JsonResponse
    {
        $this->accounts->recordLogin($user, $request->ip());

        // Limita a quantidade de dispositivos: remove os tokens mais antigos.
        $max = config('sancta.api.max_tokens_per_user', 10);
        $excess = $user->tokens()->orderByDesc('id')->skip($max - 1)->take(PHP_INT_MAX)->pluck('id');
        if ($excess->isNotEmpty()) {
            $user->tokens()->whereIn('id', $excess)->delete();
        }

        $expiresAt = now()->addDays(config('sancta.api.token_ttl_days', 60));
        $token = $user->createToken(Str::limit(strip_tags($deviceName), 100, ''), ['app'], $expiresAt);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => new UserResource($user->fresh()),
        ], $status);
    }
}
