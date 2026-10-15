@extends('layouts.site', ['title' => $church->name, 'description' => 'Horários de missa, confissão e contatos: '.$church->name.' — '.$church->fullAddress()])

@section('content')
<section class="page-head">
    <div class="container">
        <a href="{{ route('churches.index') }}" class="back-link"><x-sancta-icon name="arrow-left" /> Mapa de igrejas</a>
        <div class="church-meta">
            <span class="badge"><x-sancta-icon name="church" /> {{ $church->type->getLabel() }}</span>
            @if ($church->is_verified)<span class="badge badge-success"><x-sancta-icon name="check" /> Verificada</span>@endif
        </div>
        <h1>{{ $church->name }}</h1>
        <p class="lead"><x-sancta-icon name="pin" class="icon-inline" /> {{ $church->fullAddress() }}</p>
        <div class="actions mt-3">
            @if ($church->mapsUrl())<a class="btn btn-primary" href="{{ $church->mapsUrl() }}" target="_blank" rel="noopener"><x-sancta-icon name="directions" /> Como chegar</a>@endif
            <a class="btn btn-outline" href="{{ route('churches.correct', $church->slug) }}"><x-sancta-icon name="edit" /> Corrigir informação</a>
        </div>
    </div>
</section>

<section class="section-tight section-last">
    <div class="container grid grid-2">
        <div class="card" aria-labelledby="horarios">
            <h2 class="card-title" id="horarios">Horários</h2>
            @forelse ($schedules as $label => $items)
                <div class="schedule-group">
                    <h3>{{ $label }}</h3>
                    <table class="schedule-table">
                        <caption class="sr-only">{{ $label }}</caption>
                        @foreach ($items as $s)
                            <tr>
                                <th scope="row">{{ \App\Enums\ScheduleType::weekdayLabel($s->weekday) }}</th>
                                <td>{{ $s->timeLabel() ?: '—' }} @if ($s->notes)<span>{{ $s->notes }}</span>@endif</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @empty
                <p class="muted">Ainda não temos horários cadastrados para esta igreja. Conhece os horários? <a href="{{ route('churches.correct', $church->slug) }}">Conte para nós</a>.</p>
            @endforelse
            <p class="hint mt-3 mb-0"><x-sancta-icon name="info" class="icon-inline" /> Horários podem mudar em datas especiais. Confirme com a paróquia.</p>
        </div>

        <div class="grid">
            <div class="card" aria-labelledby="contato">
                <h2 class="card-title" id="contato">Contato</h2>
                @if ($church->phone || $church->email || $church->instagram || $church->website || $church->diocese)
                    <dl class="kv">
                        @if ($church->phone)<dt>Telefone</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $church->phone) }}">{{ $church->phone }}</a></dd>@endif
                        @if ($church->email)<dt>E-mail</dt><dd><a href="mailto:{{ $church->email }}">{{ $church->email }}</a></dd>@endif
                        @if ($church->instagram)<dt>Instagram</dt><dd><a href="https://instagram.com/{{ ltrim($church->instagram, '@') }}" target="_blank" rel="noopener nofollow">{{ $church->instagram }}</a></dd>@endif
                        @if ($church->website && preg_match("~^https?://~i", $church->website))<dt>Site</dt><dd><a href="{{ $church->website }}" target="_blank" rel="noopener nofollow">{{ parse_url($church->website, PHP_URL_HOST) }}</a></dd>@endif
                        @if ($church->diocese)<dt>Diocese</dt><dd>{{ $church->diocese }}</dd>@endif
                    </dl>
                @else
                    <p class="muted mb-0">Sem contatos cadastrados.</p>
                @endif
            </div>
            @if ($church->description)
                <div class="card"><h2 class="card-title">Sobre</h2><p class="soft mb-0">{{ $church->description }}</p></div>
            @endif
            <div class="card app-cta">
                <div><strong>Favorite esta igreja no app</strong><p>Receba avisos e encontre os horários com um toque.</p></div>
                <x-store-badge />
            </div>
        </div>
    </div>
</section>
@endsection
