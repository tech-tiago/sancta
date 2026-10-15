<?php

namespace App\Http\Controllers\Web;

use App\Enums\ThemePreference;
use App\Http\Controllers\Controller;
use App\Models\GiftCode;
use App\Services\Account\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        return view('site.account.show', [
            'user' => $user,
            'subscription' => $user->activeSubscription(),
            'payments' => $user->payments()->with('plan')->latest()->limit(20)->get(),
            // Presentes que comprei (sem o código: só os 4 últimos caracteres).
            'gifts' => GiftCode::query()->where('purchaser_id', $user->id)->with('plan')->latest()->limit(50)->get(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'theme' => ['required', Rule::enum(ThemePreference::class)],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ]);

        $request->user()->fill([
            'name' => strip_tags(trim($data['name'])),
            'theme' => $data['theme'],
            'marketing_opt_in' => $request->boolean('marketing_opt_in'),
        ])->save();

        return back()->with('status', 'Perfil atualizado.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => [$user->password ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($user->password && ! Hash::check((string) $data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Senha atual incorreta.']);
        }

        $this->accounts->changePassword($user, $data['password']);
        Auth::logoutOtherDevices($data['password']);
        $request->session()->regenerate();

        return back()->with('status', 'Senha alterada. As sessões nos outros dispositivos foram encerradas.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'confirmation' => ['required', Rule::in(['EXCLUIR'])],
            'password' => [$user->password ? 'required' : 'nullable', 'string'],
        ]);

        if ($user->password && ! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'Senha incorreta.']);
        }

        Auth::guard('web')->logout();
        $this->accounts->delete($user);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Sua conta e seus dados foram excluídos. Que Deus te abençoe!');
    }
}
