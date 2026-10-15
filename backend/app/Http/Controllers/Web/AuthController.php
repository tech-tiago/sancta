<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Account\AccountService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function showLogin(): View
    {
        return view('site.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $key = 'web-login:'.sha1(mb_strtolower($data['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Muitas tentativas. Aguarde '.RateLimiter::availableIn($key).' segundos.']);
        }

        if (! Auth::attempt(['email' => mb_strtolower(trim($data['email'])), 'password' => $data['password']], $request->boolean('remember'))) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate(); // evita fixação de sessão

        $user = $request->user();
        if (! $user->isActive()) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Conta suspensa. Fale com o suporte.']);
        }

        $this->accounts->recordLogin($user, $request->ip());

        return redirect()->intended(route('account.show'));
    }

    public function showRegister(): View
    {
        return view('site.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'accept_terms' => ['accepted'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        if (User::withTrashed()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Não foi possível criar a conta com este e-mail. Se já possui cadastro, entre ou recupere a senha.']);
        }

        $user = User::query()->create([
            'name' => strip_tags(trim($data['name'])),
            'email' => $email,
            'password' => $data['password'],
            'marketing_opt_in' => $request->boolean('marketing_opt_in'),
        ]);
        $user->forceFill(['terms_accepted_at' => now(), 'source' => 'site', 'password_changed_at' => now()])->save();

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('account.show'))->with('status', 'Conta criada! Enviamos um e-mail para confirmar seu endereço.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showForgot(): View
    {
        return view('site.auth.forgot');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:191']]);
        Password::sendResetLink(['email' => mb_strtolower(trim($data['email']))]);

        return back()->with('status', 'Se o e-mail estiver cadastrado, você receberá um link para redefinir a senha.');
    }

    public function showReset(Request $request, string $token): View
    {
        return view('site.auth.reset', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset($data, function (User $user, string $password): void {
            $this->accounts->changePassword($user, $password);
            $user->forceFill(['remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Link inválido ou expirado. Solicite um novo.']);
        }

        return redirect()->route('login')->with('status', 'Senha redefinida! Entre com a nova senha (no site ou no app).');
    }

    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->route(Auth::check() ? 'account.show' : 'login')->with('status', 'E-mail confirmado. Obrigado!');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'Enviamos um novo link de confirmação.');
    }
}
