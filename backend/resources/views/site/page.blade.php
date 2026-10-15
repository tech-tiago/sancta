@extends('layouts.site', ['title' => $page->title, 'description' => \App\Support\Html::toText($page->content, 160)])

@section('content')
<section class="page-head">
    <div class="container container-md">
        <span class="eyebrow"><x-portal class="icon" /> {{ app(\App\Services\Settings::class)->get('app.name') }}</span>
        <h1>{{ $page->title }}</h1>
        @if ($page->updated_at)<p class="muted mb-0">Atualizado em {{ $page->updated_at->format('d/m/Y') }}</p>@endif
    </div>
</section>
<section class="section-tight section-last">
    <div class="container container-md">
        {{-- Conteúdo sanitizado no salvamento e na exibição (App\Support\Html::sanitize) --}}
        <article class="card prose-card">
            <div class="prose">{!! \App\Support\Html::sanitize($page->content) !!}</div>
        </article>
    </div>
</section>
@endsection
