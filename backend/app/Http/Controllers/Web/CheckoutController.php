<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\Gateways\FakeGateway;
use App\Services\Billing\GiftService;
use App\Services\Billing\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Assinatura pelo site: Pix (QR Code na página) ou cartão (checkout do Mercado Pago).
 * Cada pagamento só pode ser visto pelo próprio dono (sem IDOR).
 */
class CheckoutController extends Controller
{
    /** Sessão do navegador que comprou o presente: só ela pode ver o código na página do pagamento. */
    private const GIFT_REVEAL_SESSION = 'gift_reveal';

    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly PaymentGatewayManager $gateways,
        private readonly GiftService $gifts,
    ) {}

    public function show(Request $request, Plan $plan): View
    {
        abort_unless($plan->is_active && $plan->available_on_web, 404);

        return view('site.checkout.show', [
            'plan' => $plan,
            // ?gift=1 (vindo de /presentear): compra para presentear alguém.
            'gift' => $request->boolean('gift'),
            'activeSubscription' => $request->user()->activeSubscription(),
            'configured' => $this->gateways->isConfigured(),
            'methods' => $this->gateways->enabledMethods(),
        ]);
    }

    public function start(Request $request, Plan $plan): RedirectResponse
    {
        abort_unless($plan->is_active && $plan->available_on_web, 404);

        $data = $request->validate([
            'method' => ['required', Rule::in([PaymentMethod::Pix->value, PaymentMethod::CreditCard->value])],
            'coupon' => ['nullable', 'string', 'max:40', 'alpha_dash'],
            'gift' => ['nullable', 'boolean'],
            'accept_terms' => ['accepted'],
        ]);

        try {
            // O valor é sempre o do plano (+ cupom), calculado no servidor — inclusive para presentes.
            $payment = $this->checkout->start($request->user(), $plan, PaymentMethod::from($data['method']), $data['coupon'] ?? null, $request->ip(), $request->boolean('gift'));
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['method' => $e->getMessage()]);
        }

        if ($payment->is_gift) {
            // A exibição única do código fica presa a ESTE navegador (outras sessões da conta não a veem).
            $request->session()->put(self::GIFT_REVEAL_SESSION.'.'.$payment->uuid, true);
        }

        if ($payment->method === PaymentMethod::CreditCard && $payment->checkout_url) {
            return redirect()->away($payment->checkout_url);
        }

        return redirect()->route('checkout.status', $payment->uuid);
    }

    public function status(Request $request, string $uuid): Response
    {
        $payment = $this->ownedPayment($request, $uuid);

        if ($payment->isPending()) {
            try {
                $payment = $this->checkout->sync($payment);
            } catch (RuntimeException) {
                // provedor indisponível: mantém o estado atual
            }
        }

        $isGift = $payment->is_gift && $payment->status === PaymentStatus::Approved;

        // Código em claro: exibido uma única vez e só no navegador que fez a compra
        // (depois, só pelo e-mail ou gerando um novo). Pentest fase 3 — SEC-12.
        $giftCode = null;
        $revealKey = self::GIFT_REVEAL_SESSION.'.'.$payment->uuid;
        if ($isGift && $request->session()->get($revealKey) === true) {
            $giftCode = $this->gifts->revealOnce($payment);
            if ($giftCode !== null) {
                $request->session()->forget($revealKey);
            }
        }

        return response()->view('site.checkout.status', [
            'payment' => $payment->load('plan'),
            'canSimulate' => $payment->provider === PaymentProvider::Fake && $this->gateways->fakeAllowed(),
            'gift' => $isGift ? $payment->giftCode : null,
            'giftCode' => $giftCode,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function poll(Request $request, string $uuid): JsonResponse
    {
        $payment = $this->ownedPayment($request, $uuid);

        if ($payment->isPending()) {
            try {
                $payment = $this->checkout->sync($payment);
            } catch (RuntimeException) {
            }
        }

        return response()->json(['status' => $payment->status->value, 'label' => $payment->status->getLabel()]);
    }

    /** Somente ambiente local: aprova/recusa um pagamento SIMULADO para demonstração. */
    public function simulate(Request $request, string $uuid): RedirectResponse
    {
        abort_unless($this->gateways->fakeAllowed(), 404);

        $payment = $this->ownedPayment($request, $uuid);
        abort_unless($payment->provider === PaymentProvider::Fake && $payment->isPending(), 404);

        $status = $request->input('result') === 'reject' ? PaymentStatus::Rejected : PaymentStatus::Approved;
        $this->checkout->apply($payment, app(FakeGateway::class)->simulate($payment, $status));

        return redirect()->route('checkout.status', $payment->uuid);
    }

    private function ownedPayment(Request $request, string $uuid): Payment
    {
        return Payment::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
