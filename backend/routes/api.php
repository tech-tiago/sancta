<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BibleController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\ChurchController;
use App\Http\Controllers\Api\V1\CommunityController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\JourneyController;
use App\Http\Controllers\Api\V1\LiturgyController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PrayerController;
use App\Http\Controllers\Api\V1\PrayerSessionController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API do aplicativo Sancta — /api/v1
|--------------------------------------------------------------------------
| Rotas públicas aceitam token opcional (conteúdo Sancta+ depende do usuário).
| Rotas autenticadas exigem token Sanctum válido e conta ativa.
*/

Route::prefix('v1')->middleware('throttle:api')->group(function () {

    // ─── Público ───
    Route::get('config', [ConfigController::class, 'show']);
    Route::get('home', [HomeController::class, 'index']);
    Route::get('liturgy', [LiturgyController::class, 'show']);

    Route::get('bible/books', [BibleController::class, 'books']);
    Route::get('bible/books/{book}/chapters/{chapter}', [BibleController::class, 'chapter'])->whereNumber('chapter');
    Route::get('bible/search', [BibleController::class, 'search'])->middleware('throttle:search');

    Route::get('categories', [PrayerController::class, 'categories']);
    Route::get('prayers', [PrayerController::class, 'index']);
    Route::get('prayers/{slug}', [PrayerController::class, 'show']);
    Route::get('journeys', [JourneyController::class, 'index']);
    Route::get('journeys/{slug}', [JourneyController::class, 'show']);

    Route::get('churches', [ChurchController::class, 'index']);
    Route::get('churches/{uuid}', [ChurchController::class, 'show'])->whereUuid('uuid');

    Route::get('news', [ContentController::class, 'news']);
    Route::get('news/{id}', [ContentController::class, 'newsShow'])->whereNumber('id');
    Route::get('live-channels', [ContentController::class, 'live']);
    Route::get('events', [ContentController::class, 'events']);
    Route::get('saints/today', [ContentController::class, 'saintOfDay']);
    Route::get('plans', [ContentController::class, 'plans']);
    Route::get('pages/{slug}', [ContentController::class, 'page']);
    Route::get('faqs', [ContentController::class, 'faqs']);
    Route::get('intentions', [CommunityController::class, 'intentions']);
    Route::get('announcements', [CommunityController::class, 'announcements']);
    Route::post('announcements/{id}/interactions', [CommunityController::class, 'announcementInteraction'])->whereNumber('id')->middleware('throttle:interactions');

    // ─── Autenticação ───
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('google', [AuthController::class, 'google']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
    });

    // ─── Autenticado ───
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);

        Route::get('me', [MeController::class, 'show']);
        Route::patch('me', [MeController::class, 'update']);
        Route::put('me/password', [MeController::class, 'updatePassword'])->middleware('throttle:auth');
        Route::post('me/avatar', [MeController::class, 'uploadAvatar'])->middleware('throttle:uploads');
        Route::delete('me', [MeController::class, 'destroy'])->middleware('throttle:auth');
        Route::get('me/stats', [MeController::class, 'stats']);
        Route::get('me/subscription', [MeController::class, 'subscription']);
        Route::post('me/devices', [MeController::class, 'registerDevice']);
        Route::delete('me/devices', [MeController::class, 'removeDevice']);

        Route::get('me/bible/progress', [BibleController::class, 'progress']);
        Route::put('me/bible/progress', [BibleController::class, 'saveProgress']);
        Route::get('me/bible/annotations', [BibleController::class, 'annotations']);
        Route::put('me/bible/annotations', [BibleController::class, 'annotate']);
        Route::post('bible/explain', [BibleController::class, 'explain'])->middleware('throttle:ai');

        Route::get('me/favorite-prayers', [PrayerController::class, 'favorites']);
        Route::post('prayers/{slug}/favorite', [PrayerController::class, 'toggleFavorite']);
        Route::post('journeys/{slug}/steps/{position}/complete', [JourneyController::class, 'completeStep'])->whereNumber('position');
        Route::post('me/prayer-sessions', [PrayerSessionController::class, 'store'])->middleware('throttle:interactions');

        Route::get('me/favorite-churches', [ChurchController::class, 'favorites']);
        Route::post('churches/{uuid}/favorite', [ChurchController::class, 'toggleFavorite'])->whereUuid('uuid');
        Route::post('church-submissions', [ChurchController::class, 'submit'])->middleware('throttle:submissions');

        Route::post('intentions', [CommunityController::class, 'submitIntention'])->middleware('throttle:submissions');
        Route::post('intentions/{uuid}/pray', [CommunityController::class, 'pray'])->whereUuid('uuid')->middleware('throttle:interactions');

        Route::post('billing/google-play/verify', [BillingController::class, 'verifyGooglePlay'])->middleware('throttle:checkout');
    });
});

// ─── Webhooks (sem sessão/CSRF; autenticados por assinatura) ───
Route::prefix('webhooks')->middleware('throttle:webhooks')->group(function () {
    Route::post('mercadopago', [WebhookController::class, 'mercadoPago'])->name('webhooks.mercadopago');
    Route::post('google-play', [WebhookController::class, 'googlePlay'])->name('webhooks.google-play');
});
