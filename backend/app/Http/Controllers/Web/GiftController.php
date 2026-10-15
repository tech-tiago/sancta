<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GiftCode;
use App\Models\Plan;
use App\Services\Billing\GiftCodeException;
use App\Services\Billing\GiftService;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * "Presentear Sancta+" — somente no site (política do Google Play: o app não vende nem
 * resgata fora do Play Billing; o acesso resgatado aqui aparece no app automaticamente).
 */
class GiftController extends Controller
{
    public function __construct(private readonly GiftService $gifts) {}

    /** /presentear — escolher o plano do presente (página pública; o checkout exige login). */
    public function index(Settings $settings): View
    {
        return view('site.gift.index', [
            'plans' => Plan::withPricing(Plan::query()->active()->where('available_on_web', true)->get()),
            'settings' => $settings,
        ]);
    }

    /** /resgatar — formulário de resgate (logado). */
    public function redeemForm(Request $request, Settings $settings): View
    {
        return view('site.gift.redeem', [
            'subscription' => $request->user()->activeSubscription(),
            'settings' => $settings,
        ]);
    }

    public function redeem(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
        ]);

        try {
            $gift = $this->gifts->redeem($request->user(), $data['code']);
        } catch (GiftCodeException $e) {
            // Mensagem genérica; o código digitado não volta para a tela.
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $endsAt = $gift->subscription?->ends_at;

        return redirect()->route('account.show')->with('status', $endsAt
            ? 'Presente resgatado! Seu acesso vale até '.$endsAt->format('d/m/Y').', no app e no site.'
            : 'Presente resgatado! Seu acesso já está liberado no app e no site.');
    }

    /** Minha conta → Presentes: gera um novo código e envia só para o e-mail do comprador. */
    public function regenerate(Request $request, string $uuid): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Confirme que o código anterior deixará de funcionar.']);

        $gift = GiftCode::query()
            ->where('uuid', $uuid)
            ->where('purchaser_id', $request->user()->id)
            ->firstOrFail();

        try {
            $this->gifts->regenerate($gift, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['gift' => $e->getMessage()]);
        }

        return back()->with('status', 'Enviamos um novo código para o seu e-mail. O código anterior deixou de valer.');
    }
}
