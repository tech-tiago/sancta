@php
    // Página de erro independente: não consulta banco nem configurações (funciona até em manutenção ou falha geral).
    $theme = request()->cookie('sancta_theme');
    $theme = in_array($theme, ['light', 'dark'], true) ? $theme : null;
@endphp
<!DOCTYPE html>
<html lang="pt-BR" @if($theme) data-theme="{{ $theme }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#1F3D9C">
    <title>@yield('title') — Sancta</title>
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="/images/brand/favicon.svg">
    <link rel="stylesheet" href="/fonts/figtree.css">
    <link rel="stylesheet" href="/css/sancta.css">
</head>
<body>
<header class="error-top">
    <div class="container">
        <a class="brand" href="/"><img src="/images/brand/sancta-wordmark-pergaminho.svg" alt="Sancta — início" width="110" height="26"></a>
    </div>
</header>
<main class="error-main container" id="conteudo">
    <div>
        <div class="arch arch-tall error-arch" aria-hidden="true">
            <img src="/images/illustrations/@yield('sky', 'noite')-retrato.svg" alt="" width="600" height="800">
            <span class="error-code">@yield('code')</span>
        </div>
        <p class="eyebrow">Erro @yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @hasSection('action')
                @yield('action')
            @else
                <a class="btn btn-primary" href="/">Voltar ao início</a>
            @endif
            <a class="btn btn-outline" href="/biblia">Ler a Bíblia</a>
        </div>
    </div>
</main>
</body>
</html>
