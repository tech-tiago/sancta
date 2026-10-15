<?php

namespace App\Services\Billing;

use App\Enums\PaymentProvider;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Throwable;

/**
 * Webhook do Mercado Pago.
 * 1) Valida a assinatura HMAC-SHA256 (cabeçalho x-signature) com o segredo do painel MP;
 * 2) Garante idempotência (webhook_events único por provedor + id);
 * 3) NUNCA confia no corpo: consulta o pagamento na API do MP antes de liberar acesso.
 */
class MercadoPagoWebhook
{
    public function __construct(private readonly CheckoutService $checkout) {}

    public function verifySignature(Request $request): bool
    {
        $secret = (string) config('services.mercadopago.webhook_secret');
        $header = (string) $request->header('x-signature');
        $requestId = (string) $request->header('x-request-id');
        $dataId = $this->dataId($request);

        if ($secret === '' || $header === '' || $dataId === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $piece) {
            [$key, $value] = array_pad(array_map('trim', explode('=', $piece, 2)), 2, null);
            if ($key && $value) {
                $parts[$key] = $value;
            }
        }

        if (empty($parts['ts']) || empty($parts['v1'])) {
            return false;
        }

        // Rejeita notificações antigas (replay) — tolerância de 15 minutos.
        $ts = (int) $parts['ts'];
        $ts = $ts > 9_999_999_999 ? intdiv($ts, 1000) : $ts;
        if (abs(time() - $ts) > 900) {
            return false;
        }

        $id = ctype_alnum($dataId) ? strtolower($dataId) : $dataId;
        $manifest = "id:{$id};".($requestId !== '' ? "request-id:{$requestId};" : '')."ts:{$parts['ts']};";

        return hash_equals(hash_hmac('sha256', $manifest, $secret), $parts['v1']);
    }

    /**
     * O id do pagamento usado na assinatura E no processamento (sempre o mesmo):
     * o da URL (como o Mercado Pago assina); o do corpo só quando a URL não traz nenhum.
     */
    private function dataId(Request $request): string
    {
        return (string) ($request->query('data_id') ?? $request->query('data.id') ?? $request->input('data.id', ''));
    }

    public function handle(Request $request): string
    {
        $type = (string) ($request->input('type') ?? $request->query('type') ?? $request->input('topic'));
        $dataId = $this->dataId($request);

        if ($type !== 'payment' || ! ctype_digit($dataId)) {
            return 'ignored';
        }

        try {
            $event = WebhookEvent::query()->create([
                'provider' => PaymentProvider::MercadoPago->value,
                'event_id' => (string) ($request->input('id') ?? "payment-{$dataId}-".$request->header('x-request-id')),
                'type' => $type.'.'.$request->input('action', 'updated'),
                'payload' => $request->only(['id', 'type', 'action', 'data', 'date_created', 'live_mode']),
                'ip_address' => $request->ip(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return 'duplicate';
        }

        try {
            $payment = Payment::query()
                ->where('provider', PaymentProvider::MercadoPago->value)
                ->where('provider_payment_id', $dataId)
                ->first();

            if (! $payment) {
                // Pagamento de cartão (Checkout Pro): o id só é conhecido agora → localiza pela external_reference.
                $remote = app(PaymentGatewayManager::class)->for('mercadopago')->fetchPayment($dataId);
                $payment = $remote?->externalReference
                    ? Payment::query()->where('uuid', $remote->externalReference)->where('provider', PaymentProvider::MercadoPago->value)->first()
                    : null;

                if ($payment && $remote) {
                    $this->checkout->apply($payment, $remote);
                    $event->markProcessed();

                    return 'processed';
                }

                $event->markProcessed('ignored', 'Pagamento não encontrado localmente');

                return 'ignored';
            }

            $this->checkout->sync($payment);
            $event->markProcessed();

            return 'processed';
        } catch (Throwable $e) {
            report($e);
            $event->markProcessed('failed', mb_substr($e->getMessage(), 0, 1000));

            return 'failed';
        }
    }
}
