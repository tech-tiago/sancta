<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\Billing\GooglePlayBilling;
use App\Services\PremiumContent;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configuração remota do app: versão mínima (força atualização), manutenção,
 * recursos ligados/desligados, textos do paywall e links — tudo controlado pelo painel.
 */
class ConfigController extends Controller
{
    public function show(Request $request, Settings $settings, PremiumContent $premium): JsonResponse
    {
        $user = $request->user('sanctum');

        return response()->json([
            'app' => [
                'name' => $settings->get('app.name'),
                'tagline' => $settings->get('app.tagline'),
                'slogan' => $settings->get('app.slogan'),
                'min_version' => $settings->get('app.min_version'),
                'latest_version' => $settings->get('app.latest_version'),
                'update_message' => $settings->get('app.update_message'),
                'maintenance' => $settings->bool('app.maintenance'),
                'maintenance_message' => $settings->get('app.maintenance_message'),
                'play_store_url' => $settings->get('app.play_store_url'),
                'support_email' => $settings->get('app.support_email'),
                'support_whatsapp' => $settings->get('app.support_whatsapp'),
                'website_url' => url('/'),
                'privacy_url' => route('pages.show', 'politica-de-privacidade'),
                'terms_url' => route('pages.show', 'termos-de-uso'),
                'delete_account_url' => route('pages.show', 'excluir-conta'),
            ],
            'features' => [
                'ai_explanations' => $settings->bool('features.ai_explanations'),
                'live' => $settings->bool('features.live'),
                'news' => $settings->bool('features.news'),
                'churches' => $settings->bool('features.churches'),
                'intentions' => $settings->bool('features.intentions'),
                'events' => $settings->bool('features.events'),
                'guest_mode' => $settings->bool('features.guest_mode'),
            ],
            'theme' => ['default' => $settings->get('theme.default')],
            'maps' => [
                'tiles_light' => config('sancta.maps.tiles_light'),
                'tiles_dark' => config('sancta.maps.tiles_dark'),
                'dark_filter' => (bool) config('sancta.maps.dark_filter'),
                'attribution' => strip_tags(html_entity_decode((string) config('sancta.maps.attribution'))),
            ],
            // Paywall (painel → Configurações → Sancta+). `benefits` segue como lista de strings (app atual);
            // `benefit_items` traz ícone/título/descrição. Ver docs/API.md.
            'premium' => $premium->toArray(),
            'billing' => [
                // Identificador ofuscado da conta, enviado ao Google Play na compra (anti-fraude).
                'obfuscated_account_id' => $user ? GooglePlayBilling::obfuscatedAccountId($user) : null,
                // IDs de assinatura do Google Play cadastrados nos planos (painel → Planos).
                'product_ids' => Plan::query()->where('is_active', true)->whereNotNull('google_play_product_id')
                    ->distinct()->pluck('google_play_product_id')->values(),
            ],
            'auth' => [
                // Client ID Web do Google (público): o app pede o id_token para ele (painel → Integrações).
                'google_server_client_id' => config('services.google.web_client_id'),
            ],
        ]);
    }
}
