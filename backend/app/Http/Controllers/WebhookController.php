<?php

namespace App\Http\Controllers;

use App\Services\Billing\GooglePlayRtdn;
use App\Services\Billing\MercadoPagoWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhooks de pagamento. Requisições sem assinatura/token válido são recusadas (401)
 * e registradas no log — nada é processado a partir de dados não autenticados.
 */
class WebhookController extends Controller
{
    public function mercadoPago(Request $request, MercadoPagoWebhook $webhook): JsonResponse
    {
        if (! $webhook->verifySignature($request)) {
            Log::warning('Webhook Mercado Pago com assinatura inválida', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Assinatura inválida.'], 401);
        }

        return response()->json(['status' => $webhook->handle($request)]);
    }

    public function googlePlay(Request $request, GooglePlayRtdn $rtdn): JsonResponse
    {
        if (! $rtdn->authorize($request)) {
            Log::warning('RTDN Google Play não autorizada', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        // Pub/Sub reenvia em caso de erro 5xx; respondemos 200 após registrar o evento.
        return response()->json(['status' => $rtdn->handle($request)]);
    }
}
