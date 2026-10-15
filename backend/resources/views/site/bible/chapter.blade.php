@extends('layouts.site', ['title' => "{$book->name} {$chapter}", 'description' => \Illuminate\Support\Str::limit($verses->first()?->text ?? $book->name, 155)])

@section('content')
<div class="container bible-layout">
    <aside class="bible-aside" aria-label="Livros da Bíblia">
        @foreach (['AT' => 'Antigo Testamento', 'NT' => 'Novo Testamento'] as $key => $label)
            <h2>{{ $label }}</h2>
            @foreach ($books->where('testament', $key) as $b)
                <a href="{{ route('bible.chapter', [$b->slug, 1]) }}" @class(['active' => $b->slug === $book->slug]) @if($b->slug === $book->slug) aria-current="true" @endif>{{ $b->name }}</a>
            @endforeach
        @endforeach
    </aside>

    <div>
        <nav class="reader-top" aria-label="Navegação da Bíblia">
            <a href="{{ route('bible.index') }}" class="back-link"><x-sancta-icon name="arrow-left" /> Todos os livros</a>
            <form class="search-bar" action="{{ route('bible.search') }}" method="get" role="search">
                <label class="sr-only" for="q">Buscar na Bíblia</label>
                <input id="q" type="search" name="q" minlength="3" maxlength="100" placeholder="Buscar na Bíblia">
            </form>
        </nav>

        <details class="book-picker">
            <summary class="btn btn-outline btn-sm"><x-sancta-icon name="list" /> Livros</summary>
            <div class="book-grid">
                @foreach ($books as $b)
                    <a href="{{ route('bible.chapter', [$b->slug, 1]) }}">{{ $b->name }}</a>
                @endforeach
            </div>
        </details>

        <article class="reader" data-reader data-size="2" data-font="default" aria-labelledby="capitulo">
            <div class="reader-head">
                <div>
                    <span class="eyebrow">{{ $book->testamentLabel() }}</span>
                    <h1 id="capitulo">{{ $book->name }} <span>{{ $chapter }}</span></h1>
                </div>
                <div class="reader-tools" role="group" aria-label="Preferências de leitura" data-reader-tools hidden>
                    <button type="button" data-reader-smaller aria-label="Diminuir o texto"><span aria-hidden="true" class="a-sm">A</span></button>
                    <button type="button" data-reader-bigger aria-label="Aumentar o texto"><span aria-hidden="true" class="a-lg">A</span></button>
                    <span class="sep" aria-hidden="true"></span>
                    <button type="button" data-reader-classic aria-pressed="false">Clássica</button>
                </div>
            </div>

            @if ($book->chapters_count > 1)
                <details class="chapter-picker" @if($book->chapters_count <= 30) open @endif>
                    <summary>Capítulos ({{ $book->chapters_count }}) <x-sancta-icon name="chevron-down" /></summary>
                    <nav class="chapter-pills" aria-label="Capítulos">
                        @for ($c = 1; $c <= $book->chapters_count; $c++)
                            <a href="{{ route('bible.chapter', [$book->slug, $c]) }}" @class(['active' => $c === $chapter]) @if($c === $chapter) aria-current="page" @endif>{{ $c }}</a>
                        @endfor
                    </nav>
                </details>
            @endif

            <div class="reader-text">
                @foreach ($verses as $v)
                    <p id="v{{ $v->verse }}"><sup>{{ $v->verse }}</sup>{{ $v->text }}</p>
                @endforeach
            </div>

            <nav class="chapter-nav" aria-label="Capítulo anterior e próximo">
                @if ($prev)
                    <a class="btn btn-outline" href="{{ route('bible.chapter', $prev) }}" rel="prev"><x-sancta-icon name="arrow-left" /> Anterior</a>
                @else <span></span> @endif
                @if ($next)
                    <a class="btn btn-primary" href="{{ route('bible.chapter', $next) }}" rel="next">Próximo <x-sancta-icon name="arrow-right" /></a>
                @endif
            </nav>
        </article>

        <div class="card app-cta mt-3">
            <div><strong>Destaque, anote e ouça este capítulo</strong><p>No app Sancta você marca versículos com cores, faz notas e ouve a leitura.</p></div>
            <x-store-badge />
        </div>
        @if ($version->license_notice)<p class="license">{{ $version->license_notice }}</p>@endif
    </div>
</div>
@endsection
