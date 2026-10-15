@extends('layouts.site', ['title' => 'Busca na Bíblia'])

@section('content')
<section class="page-head">
    <div class="container container-md">
        <a href="{{ route('bible.index') }}" class="back-link"><x-sancta-icon name="arrow-left" /> Bíblia</a>
        <h1>Buscar na Bíblia</h1>
        <form class="search-bar mt-2" action="{{ route('bible.search') }}" method="get" role="search">
            <label class="sr-only" for="q">Termo</label>
            <input id="q" type="search" name="q" value="{{ $term }}" minlength="3" maxlength="100" required placeholder="Digite uma palavra">
            <button class="btn btn-primary" type="submit"><x-sancta-icon name="search" /> Buscar</button>
        </form>
    </div>
</section>
<section class="section-tight section-last">
    <div class="container container-md">
        @if ($term !== '' && mb_strlen($term) < 3)
            <p class="muted">Digite pelo menos 3 letras.</p>
        @elseif ($term !== '')
            <p class="muted" role="status">{{ $results->count() }} {{ $results->count() === 1 ? 'resultado' : 'resultados' }} para “{{ $term }}”{{ $results->count() >= 60 ? ' (mostrando os 60 primeiros)' : '' }}.</p>
            @forelse ($results as $r)
                <div class="search-hit">
                    <a href="{{ route('bible.chapter', [$r->slug, $r->chapter]) }}#v{{ $r->verse }}">{{ $r->name }} {{ $r->chapter }},{{ $r->verse }}</a>
                    {{-- Texto escapado; o destaque é aplicado sobre o HTML já escapado --}}
                    <p>{!! preg_replace('/('.preg_quote(e($term), '/').')/iu', '<mark>$1</mark>', e($r->text)) !!}</p>
                </div>
            @empty
                <div class="card center">
                    <p class="mb-0">Nenhum versículo encontrado. Tente outra palavra.</p>
                </div>
            @endforelse
        @endif
    </div>
</section>
@endsection
