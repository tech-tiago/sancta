<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\RequireAdminMfa;
use App\Http\Middleware\RestrictAdminAccess;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Enums\ThemeMode;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Sancta Gestão — painel administrativo do Sancta.
 * Segurança: caminho configurável, restrição por IP, 2FA (TOTP) com códigos de
 * recuperação, sessão autenticada, CSRF, limite de tentativas de login (Filament)
 * e autorização por papel em cada recurso (policies).
 */
class AdminPanelProvider extends PanelProvider
{
    /**
     * Paletas da marca para o Filament (o gerador automático do Color::hex() deixaria o 600 claro
     * demais e o botão principal sairia azul-pastel). 600 = Azul Manto, 800 = Noite, 900 = Tinta.
     */
    private const MANTO = [
        50 => '#EEF1FA', 100 => '#E8ECF8', 200 => '#C8D5FF', 300 => '#9DB2FF', 400 => '#7F96EC',
        500 => '#3F5BC4', 600 => '#1F3D9C', 700 => '#18317F', 800 => '#132760', 900 => '#0E1A3A', 950 => '#0A1430',
    ];

    /** Cinzas derivados da Tinta (pouca saturação): 500 = 4,9:1 sobre branco; 900/950 = superfícies do tema escuro. */
    private const TINTA = [
        50 => '#F7F8FB', 100 => '#EEF0F5', 200 => '#DFE2EB', 300 => '#C5CAD8', 400 => '#9AA1B8',
        500 => '#687089', 600 => '#5A6487', 700 => '#444D6E', 800 => '#2A3457', 900 => '#18234A', 950 => '#0E1A3A',
    ];

    /** Ouro (#E3B65B no 400) só para avisos e destaques. */
    private const OURO = [
        50 => '#FDF8EC', 100 => '#FAEFD3', 200 => '#F4DDA6', 300 => '#EECB80', 400 => '#E3B65B',
        500 => '#D19F3E', 600 => '#B5832B', 700 => '#8F6522', 800 => '#6E4D1C', 900 => '#523A17', 950 => '#2E200C',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(config('sancta.admin.path'))
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            ->multiFactorAuthentication(
                [AppAuthentication::make()->brandName('Sancta')->recoverable()],
                // Rotas sempre registradas; quem decide a cada requisição é o RequireAdminMfa
                // (o 2FA obrigatório é ligado no painel → Integrações e segurança).
                isRequired: true,
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(RequireAdminMfa::class)
            // Identidade "Portal" (docs/design/MARCA.md): Azul Manto como primária, Ouro só como aviso/destaque,
            // cinzas puxados para a Tinta e Figtree servida localmente (nada de fontes de terceiros).
            ->brandName('Sancta Gestão')
            ->brandLogo(asset('images/brand/sancta-gestao-claro.svg'))
            ->darkModeBrandLogo(asset('images/brand/sancta-gestao-escuro.svg'))
            ->brandLogoHeight('1.75rem')
            ->favicon(asset('images/brand/favicon.svg'))
            ->colors([
                'primary' => self::MANTO,
                'gray' => self::TINTA,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => self::OURO,
                'danger' => Color::Rose,
            ])
            ->font('Figtree', url: asset('fonts/figtree.css'), provider: LocalFontProvider::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): HtmlString => new HtmlString(
                '<link rel="icon" href="'.e(asset('favicon.ico')).'" sizes="32x32">'
                .'<link rel="apple-touch-icon" href="'.e(asset('images/brand/apple-touch-icon.png')).'">'
                .'<meta name="theme-color" content="#1F3D9C">'
                .'<link rel="stylesheet" href="'.e(asset('css/sancta-gestao.css')).'?v='.(@filemtime(public_path('css/sancta-gestao.css')) ?: '1').'">'
            ))
            ->darkMode(true)
            ->defaultThemeMode(ThemeMode::System)
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->globalSearch(false)
            ->navigationGroups([
                NavigationGroup::make('Assinaturas'),
                NavigationGroup::make('Usuários'),
                NavigationGroup::make('Conteúdo'),
                NavigationGroup::make('Igrejas'),
                NavigationGroup::make('Comunicação'),
                NavigationGroup::make('Mídia'),
                NavigationGroup::make('Site'),
                NavigationGroup::make('Sistema')->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                RestrictAdminAccess::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureAccountIsActive::class,
            ])
            // As ações dos componentes chegam pelo endpoint do Livewire: a restrição de IP e o bloqueio
            // de conta suspensa precisam valer ali também (não só no carregamento da página).
            ->persistentMiddleware([
                RestrictAdminAccess::class,
                EnsureAccountIsActive::class,
            ]);
    }
}
