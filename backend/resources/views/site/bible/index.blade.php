@extends('layouts.site', ['title' => 'Bíblia Sagrada online', 'description' => 'Leia a Bíblia Sagrada completa online: Antigo e Novo Testamento, com busca por palavras.'])

@section('content')
<section class="page-head">
    <div class="container">
        <span class="eyebrow"><x-sancta-icon name="book" /> {{ $version->name }}</span>
        <h1>Bíblia Sagrada</h1>
        <p class="lead">Escolha um livro para começar a leitura ou procure uma palavra.</p>
        <form class="search-bar mt-3" action="{{ route('bible.search') }}" method="get" role="search">
            <label class="sr-only" for="q">Buscar na Bíblia</label>
            <input id="q" type="search" name="q" minlength="3" maxlength="100" placeholder="Ex.: amor, pastor, misericórdia…" required>
            <button class="btn btn-primary" type="submit"><x-sancta-icon name="search" /> Buscar</button>
        </form>
    </div>
</section>

<section class="section-tight section-last">
    <div class="container">
        @foreach (['AT' => 'Antigo Testamento', 'NT' => 'Novo Testamento'] as $key => $label)
            @continue(! isset($books[$key]))
            <div class="testament" aria-labelledby="t-{{ $key }}">
                <div class="testament-head">
                    <h2 id="t-{{ $key }}">{{ $label }}</h2>
                    <span class="muted">{{ $books[$key]->count() }} livros</span>
                </div>
                <div class="book-grid">
                    @foreach ($books[$key] as $book)
                        <a href="{{ route('bible.chapter', [$book->slug, 1]) }}">{{ $book->name }}<small>{{ $book->chapters_count }} {{ $book->chapters_count === 1 ? 'capítulo' : 'capítulos' }}</small></a>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if ($version->license_notice)
            <p class="license mt-4">{{ $version->license_notice }}</p>
        @endif
    </div>
</section>
@endsection
