@php
    $settings = app(\App\Services\Settings::class);
    $themeCookie = request()->cookie('sancta_theme');
    $theme = in_array($themeCookie, ['light', 'dark', 'system'], true) ? $themeCookie : (auth()->user()?->theme?->value ?? $settings->get('theme.default'));
    $theme = $theme === 'sepia' ? 'light' : $theme;
    $appName = $settings->get('app.name');
    $premiumName = $settings->get('premium.name');
    $pageTitle = isset($title) ? $title.' — '.$appName : $appName.' — '.$settings->get('app.slogan');
    $pageDescription = $description ?? 'Sancta: Bíblia, liturgia diária, orações guiadas, novenas e igrejas perto de você. Sua jornada de fé, todos os dias.';
    // Cache-busting pelos arquivos estáticos (sem pipeline de build).
    $v = fn (string $path) => asset($path).'?v='.(@filemtime(public_path($path)) ?: '1');
@endphp
<!DOCTYPE html>
<html lang="pt-BR" class="no-js" @if($theme !== 'system') data-theme="{{ $theme }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="theme-color" content="#1F3D9C" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#132760" media="(prefers-color-scheme: dark)">
    <meta name="color-scheme" content="light dark">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="{{ $appName }}">
    <meta property="og:title" content="{{ $title ?? $appName }}">
    <meta property="og:description" content="{{ $description ?? $settings->get('app.slogan') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/brand/og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $appName }} — {{ $settings->get('app.slogan') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/brand/favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <link rel="preload" href="{{ asset('fonts/figtree/figtree-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/figtree/figtree-latin-700-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ $v('fonts/figtree.css') }}">
    <link rel="stylesheet" href="{{ $v('css/sancta.css') }}">
    @stack('head')
    <script nonce="{{ $cspNonce ?? '' }}">
        // Antes da pintura: marca JS ativo e aplica o tema salvo (evita "flash" de tema errado).
        (function () {
            var d = document.documentElement;
            d.className = d.className.replace('no-js', 'js');
            try {
                var m = document.cookie.match(/(?:^|; )sancta_theme=([^;]+)/);
                var t = m ? decodeURIComponent(m[1]) : null;
                if (t === 'light' || t === 'dark') d.setAttribute('data-theme', t);
                if (t === 'system') d.removeAttribute('data-theme');
            } catch (e) {}
        })();
    </script>
    <script src="{{ $v('js/sancta.js') }}" nonce="{{ $cspNonce ?? '' }}" defer></script>
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<header class="site-header on-blue">
    <div class="container nav">
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ asset('images/brand/sancta-wordmark-pergaminho.svg') }}" alt="{{ $appName }} — início" width="110" height="26">
        </a>

        <nav class="nav-links" id="nav-links" aria-label="Principal">
            <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Início</a>
            <a href="{{ route('bible.index') }}" @if(request()->routeIs('bible.*')) aria-current="page" @endif>Bíblia</a>
            <a href="{{ route('churches.index') }}" @if(request()->routeIs('churches.*')) aria-current="page" @endif>Igrejas</a>
            @if ($settings->bool('features.live'))
                <a href="{{ route('live') }}" @if(request()->routeIs('live')) aria-current="page" @endif>Ao vivo</a>
            @endif
            <a href="{{ route('plans') }}" @if(request()->routeIs('plans', 'checkout.*', 'gift.*')) aria-current="page" @endif>{{ $premiumName }}</a>
            @auth
                <a href="{{ route('account.show') }}" @if(request()->routeIs('account.*')) aria-current="page" @endif>Minha conta</a>
            @else
                <a href="{{ route('login') }}" @if(request()->routeIs('login', 'register', 'password.*')) aria-current="page" @endif>Entrar</a>
            @endauth
            <a class="btn btn-light nav-cta" href="{{ $settings->get('app.play_store_url') }}" target="_blank" rel="noopener">Baixar o app</a>
        </nav>

        <div class="nav-actions">
            <button class="icon-btn" type="button" id="theme-toggle" aria-label="Alterar tema" title="Tema">
                <x-sancta-icon name="monitor" />
            </button>
            <a class="btn btn-light btn-sm nav-app" href="{{ $settings->get('app.play_store_url') }}" target="_blank" rel="noopener">Baixar o app</a>
            <button class="icon-btn menu-toggle" type="button" id="menu-toggle" aria-label="Abrir menu" aria-controls="nav-links" aria-expanded="false">
                <x-sancta-icon name="menu" class="icon-open" />
                <x-sancta-icon name="x" class="icon-close" />
            </button>
        </div>
    </div>
</header>

<main id="conteudo" tabindex="-1">
    @yield('content')
</main>

@unless(isset($hideFooter) && $hideFooter)
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <img class="wordmark" src="{{ asset('images/brand/sancta-wordmark-pergaminho.svg') }}" alt="{{ $appName }}" width="126" height="30" loading="lazy">
                <p class="slogan">{{ $settings->get('app.slogan') }}</p>
                <p>Orações, Bíblia, liturgia diária e igrejas perto de você.</p>
                <x-store-badge />
            </div>
            <nav aria-labelledby="rodape-explore">
                <h2 class="footer-title" id="rodape-explore">Explore</h2>
                <ul>
                    <li><a href="{{ route('bible.index') }}">Bíblia online</a></li>
                    <li><a href="{{ route('churches.index') }}">Mapa de igrejas</a></li>
                    <li><a href="{{ route('churches.create') }}">Cadastrar igreja</a></li>
                    @if ($settings->bool('features.live'))
                        <li><a href="{{ route('live') }}">Ao vivo</a></li>
                    @endif
                </ul>
            </nav>
            <nav aria-labelledby="rodape-plus">
                <h2 class="footer-title" id="rodape-plus">{{ $premiumName }}</h2>
                <ul>
                    <li><a href="{{ route('plans') }}">Planos {{ $premiumName }}</a></li>
                    <li><a href="{{ route('gift.index') }}">Presentear {{ $premiumName }}</a></li>
                    <li><a href="{{ route('gift.redeem') }}">Resgatar presente</a></li>
                    @auth
                        <li><a href="{{ route('account.show') }}">Minha conta</a></li>
                    @else
                        <li><a href="{{ route('login') }}">Entrar</a></li>
                        <li><a href="{{ route('register') }}">Criar conta</a></li>
                    @endauth
                </ul>
            </nav>
            <nav aria-labelledby="rodape-institucional">
                <h2 class="footer-title" id="rodape-institucional">Institucional</h2>
                <ul>
                    <li><a href="{{ route('pages.show', 'sobre') }}">Sobre</a></li>
                    <li><a href="{{ route('pages.show', 'termos-de-uso') }}">Termos de uso</a></li>
                    <li><a href="{{ route('pages.show', 'politica-de-privacidade') }}">Privacidade</a></li>
                    <li><a href="{{ route('pages.show', 'excluir-conta') }}">Excluir conta</a></li>
                    <li><a href="mailto:{{ $settings->get('app.support_email') }}">{{ $settings->get('app.support_email') }}</a></li>
                </ul>
            </nav>
        </div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} {{ $appName }}. Todos os direitos reservados.</span>
            <span><x-portal mono /> Feito com fé no Brasil</span>
        </div>
    </div>
</footer>
@endunless

@if (session('status') && empty($inlineStatus))
    <div class="toast" role="status" id="flash">
        <x-sancta-icon name="check-circle" />
        <span>{{ session('status') }}</span>
        <button class="icon-btn" type="button" data-dismiss aria-label="Fechar aviso"><x-sancta-icon name="x" /></button>
    </div>
@endif

@stack('scripts')
</body>
</html>
