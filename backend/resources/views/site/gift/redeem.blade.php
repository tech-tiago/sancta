@extends('layouts.site', ['title' => 'Resgatar presente'])

@php($premiumName = $settings->get('premium.name'))

@section('content')
<section class="page-head">
    <div class="container container-md">
        <x-seal :label="$premiumName" />
        <h1 class="mt-2">Resgatar presente</h1>
        <p class="lead">Digite o código que você recebeu. O período do presente é somado à sua conta e vale no app e no site.</p>
    </div>
</section>

<section class="section-tight section-last">
    <div class="container container-md redeem-wrap">
        <form class="card card-elevated form" method="post" action="{{ route('gift.redeem.store') }}" autocomplete="off">
            @csrf
            @if ($errors->any())
                <div class="alert alert-error" role="alert"><x-sancta-icon name="alert" /><div>{{ $errors->first() }}</div></div>
            @endif

            @if ($subscription)
                <div class="alert alert-info"><x-sancta-icon name="info" /><div>Você já tem acesso até <strong>{{ $subscription->ends_at?->format('d/m/Y') ?? 'sem data de término' }}</strong>. O presente começa a contar a partir dessa data.</div></div>
            @endif

            <label for="code">Código do presente
                <input id="code" class="input-code" type="text" name="code" required maxlength="40" placeholder="SANC-XXXX-XXXX-XXXX" autocomplete="off" autocapitalize="characters" spellcheck="false" @error('code') aria-invalid="true" aria-describedby="code-erro" @enderror>
            </label>
            @error('code')<span class="sr-only" id="code-erro">{{ $message }}</span>@enderror
            <p class="hint mb-0">Conta: <strong>{{ auth()->user()->email }}</strong>. Confira se é a mesma conta que você usa no app.</p>

            <button class="btn btn-primary btn-lg btn-block" type="submit"><x-sancta-icon name="gift" /> Resgatar presente</button>
        </form>

        <aside class="card" aria-labelledby="resgate-ajuda">
            <h2 class="card-title" id="resgate-ajuda">Como funciona</h2>
            <ol class="number-list mt-2">
                <li><span>Digite o código <strong>SANC-…</strong> exatamente como recebeu.</span></li>
                <li><span>O período do plano é somado ao seu acesso atual.</span></li>
                <li><span>Abra o app com a mesma conta: o {{ $premiumName }} já aparece liberado.</span></li>
            </ol>
            <hr>
            <p class="small muted mb-0">Quer presentear alguém? <a href="{{ route('gift.index') }}">Presentear {{ $premiumName }}</a>.</p>
        </aside>
    </div>
</section>
@endsection
