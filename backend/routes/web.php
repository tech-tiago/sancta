<?php

use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BibleController;
use App\Http\Controllers\Web\CheckoutController;
use App\Http\Controllers\Web\ChurchController;
use App\Http\Controllers\Web\GiftController;
use App\Http\Controllers\Web\LiveController;
use App\Http\Controllers\Web\SiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site público do Sancta
|--------------------------------------------------------------------------
| Apresentação do app, Bíblia online, mapa de igrejas (com sugestões),
| planos Sancta+ (Pix/cartão) e área do assinante.
| O painel administrativo é registrado pelo Filament em SANCTA_ADMIN_PATH.
*/

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/planos', [SiteController::class, 'plans'])->name('plans');
Route::get('/p/{slug}', [SiteController::class, 'page'])->name('pages.show');
Route::redirect('/privacidade', '/p/politica-de-privacidade');
Route::redirect('/termos', '/p/termos-de-uso');
Route::redirect('/excluir-conta', '/p/excluir-conta');
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');
Route::post('/tema', [SiteController::class, 'theme'])->name('theme')->middleware('throttle:web-forms');

// ─── Ao vivo e Presentear Sancta+ (páginas públicas) ───
Route::get('/ao-vivo', [LiveController::class, 'index'])->name('live');
Route::get('/presentear', [GiftController::class, 'index'])->name('gift.index');

// ─── Bíblia ───
Route::prefix('biblia')->name('bible.')->group(function () {
    Route::get('/', [BibleController::class, 'index'])->name('index');
    Route::get('/busca', [BibleController::class, 'search'])->name('search')->middleware('throttle:search');
    Route::get('/{book}/{chapter?}', [BibleController::class, 'chapter'])->name('chapter')->whereNumber('chapter');
});

// ─── Igrejas ───
Route::prefix('igrejas')->name('churches.')->group(function () {
    Route::get('/', [ChurchController::class, 'index'])->name('index');
    Route::get('/mapa.json', [ChurchController::class, 'mapData'])->name('map-data');
    Route::get('/cadastrar', [ChurchController::class, 'create'])->name('create');
    Route::post('/cadastrar', [ChurchController::class, 'store'])->name('store')->middleware('throttle:web-forms');
    Route::get('/{slug}', [ChurchController::class, 'show'])->name('show');
    Route::get('/{slug}/corrigir', [ChurchController::class, 'correct'])->name('correct');
    Route::post('/{slug}/corrigir', [ChurchController::class, 'storeCorrection'])->name('correct.store')->middleware('throttle:web-forms');
});

// ─── Autenticação ───
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/entrar', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::get('/cadastrar', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/cadastrar', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::get('/esqueci-senha', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/esqueci-senha', [AuthController::class, 'sendReset'])->name('password.email')->middleware('throttle:password-reset');
});
// A redefinição fica fora do grupo "guest": o link do e-mail também é usado por quem vem do app.
Route::get('/redefinir-senha/{token}', [AuthController::class, 'showReset'])->name('password.reset');
Route::post('/redefinir-senha', [AuthController::class, 'reset'])->name('password.update')->middleware('throttle:auth');
Route::get('/email/verificar/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify')->middleware(['signed', 'throttle:6,1']);

// ─── Área do assinante ───
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/sair', [AuthController::class, 'logout'])->name('logout');
    Route::post('/email/reenviar', [AuthController::class, 'resendVerification'])->name('verification.send')->middleware('throttle:3,1');

    Route::get('/minha-conta', [AccountController::class, 'show'])->name('account.show');
    Route::post('/minha-conta/perfil', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::post('/minha-conta/senha', [AccountController::class, 'updatePassword'])->name('account.password')->middleware('throttle:auth');
    Route::post('/minha-conta/excluir', [AccountController::class, 'destroy'])->name('account.destroy')->middleware('throttle:auth');

    Route::get('/assinar/{plan:slug}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/assinar/{plan:slug}', [CheckoutController::class, 'start'])->name('checkout.start')->middleware('throttle:checkout');
    Route::get('/pagamento/{uuid}', [CheckoutController::class, 'status'])->name('checkout.status')->whereUuid('uuid');
    Route::get('/pagamento/{uuid}/situacao', [CheckoutController::class, 'poll'])->name('checkout.poll')->whereUuid('uuid')->middleware('throttle:30,1');
    Route::post('/pagamento/{uuid}/simular', [CheckoutController::class, 'simulate'])->name('checkout.simulate')->whereUuid('uuid');

    // Presentes: resgate (anti força bruta por usuário e IP) e novo código para o comprador.
    Route::get('/resgatar', [GiftController::class, 'redeemForm'])->name('gift.redeem');
    Route::post('/resgatar', [GiftController::class, 'redeem'])->name('gift.redeem.store')->middleware('throttle:gift-redeem');
    Route::post('/minha-conta/presentes/{uuid}/novo-codigo', [GiftController::class, 'regenerate'])->name('account.gifts.regenerate')
        ->whereUuid('uuid')->middleware('throttle:gift-regenerate');
});
