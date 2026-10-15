@extends('layouts.site', ['title' => 'Minha conta'])

@php
    $premiumName = app(\App\Services\Settings::class)->get('premium.name');
    $initial = mb_strtoupper(mb_substr($user->firstName() ?: $user->email, 0, 1));
@endphp

@section('content')
<section class="page-head">
    <div class="container">
        <div class="account-head">
            <div class="account-id">
                <span class="avatar" aria-hidden="true">{{ $initial }}</span>
                <div>
                    <h1>Olá, {{ $user->firstName() }}!</h1>
                    <p>{{ $user->email }}</p>
                </div>
            </div>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-outline" type="submit"><x-sancta-icon name="logout" /> Sair</button></form>
        </div>
        <nav class="subnav" aria-label="Seções da conta">
            <a href="#assinatura">Assinatura</a>
            <a href="#pagamentos">Pagamentos</a>
            <a href="#presentes">Presentes</a>
            <a href="#perfil">Perfil</a>
            <a href="#seguranca">Senha</a>
        </nav>
    </div>
</section>

<section class="section-tight section-last">
    <div class="container account-grid">
        <div>
            @if ($errors->any() && ! $errors->has('gift'))
                <div class="alert alert-error" role="alert"><x-sancta-icon name="alert" /><div>{{ $errors->first() }}</div></div>
            @endif

            @unless ($user->hasVerifiedEmail())
                <div class="alert alert-info">
                    <x-sancta-icon name="mail" />
                    <div class="row between grow">
                        <span>Confirme seu e-mail para proteger sua conta.</span>
                        <form method="post" action="{{ route('verification.send') }}">@csrf<button class="btn btn-ghost btn-sm" type="submit">Reenviar link</button></form>
                    </div>
                </div>
            @endunless

            {{-- Assinatura --}}
            <div id="assinatura" @class(['card', 'sub-card', 'is-active on-blue' => (bool) $subscription])>
                <div class="card-head">
                    <h2>{{ $premiumName }}</h2>
                    @if ($subscription)
                        <x-seal :label="$subscription->status->getLabel()" />
                    @else
                        <span class="badge badge-muted">Plano gratuito</span>
                    @endif
                </div>
                @if ($subscription)
                    <dl class="kv">
                        <dt>Plano</dt><dd>{{ $subscription->plan?->name ?? 'Cortesia' }}</dd>
                        <dt>Origem</dt><dd>{{ $subscription->provider->getLabel() }}</dd>
                        <dt>Acesso até</dt><dd>{{ $subscription->ends_at ? $subscription->ends_at->format('d/m/Y') : 'Vitalício' }}</dd>
                        @if ($subscription->auto_renew)<dt>Renovação</dt><dd>Automática (gerencie no Google Play)</dd>@endif
                    </dl>
                    <div class="actions mt-3">
                        <a class="btn btn-light" href="{{ route('plans') }}">Renovar ou estender</a>
                        <a class="btn btn-outline" href="{{ route('gift.index') }}"><x-sancta-icon name="gift" /> Presentear alguém</a>
                    </div>
                @else
                    <p class="soft">Desbloqueie orações guiadas, jornadas completas, reflexões diárias e muito mais — no app e no site.</p>
                    <div class="actions">
                        <a class="btn btn-primary" href="{{ route('plans') }}">Conhecer os planos</a>
                        <a class="btn btn-ghost" href="{{ route('gift.redeem') }}"><x-sancta-icon name="key" /> Tenho um código</a>
                    </div>
                @endif
            </div>

            {{-- Pagamentos --}}
            <div class="card" id="pagamentos">
                <div class="card-head"><h2>Pagamentos</h2></div>
                @if ($payments->isEmpty())
                    <p class="muted mb-0">Nenhum pagamento ainda.</p>
                @else
                    <div class="table-wrap">
                        <table class="table table-stack">
                            <caption class="sr-only">Seus pagamentos</caption>
                            <thead><tr><th scope="col">Data</th><th scope="col">Plano</th><th scope="col">Forma</th><th scope="col">Valor</th><th scope="col">Status</th></tr></thead>
                            <tbody>
                            @foreach ($payments as $p)
                                <tr>
                                    <td data-label="Data">{{ $p->created_at->format('d/m/Y') }}</td>
                                    <td data-label="Plano"><span>{{ $p->plan?->name }}@if ($p->is_gift) <span class="badge badge-muted">Presente</span>@endif</span></td>
                                    <td data-label="Forma">{{ $p->method->getLabel() }}</td>
                                    <td data-label="Valor" class="nowrap">{{ $p->formattedAmount() }}</td>
                                    <td data-label="Status">
                                        @if ($p->isPending() && in_array($p->provider->value, ['mercadopago', 'fake'], true))
                                            <a href="{{ route('checkout.status', $p->uuid) }}">{{ $p->status->getLabel() }}</a>
                                        @else
                                            <span @class(['badge', 'badge-success' => $p->status->value === 'approved', 'badge-muted' => $p->status->value !== 'approved'])>{{ $p->status->getLabel() }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Presentes que comprei: o código nunca é exibido aqui (só os 4 últimos caracteres). --}}
            <div class="card" id="presentes">
                <div class="card-head">
                    <h2>Presentes que comprei</h2>
                    <a class="btn btn-ghost btn-sm" href="{{ route('gift.index') }}"><x-sancta-icon name="gift" /> Presentear</a>
                </div>
                @if ($errors->has('gift'))
                    <div class="alert alert-error" role="alert"><x-sancta-icon name="alert" /><div>{{ $errors->first('gift') }}</div></div>
                @endif
                @if ($gifts->isEmpty())
                    <p class="muted mb-0">Você ainda não comprou presentes. Ganhou um? <a href="{{ route('gift.redeem') }}">Resgatar presente</a>.</p>
                @else
                    <div class="table-wrap">
                        <table class="table table-stack">
                            <caption class="sr-only">Presentes que você comprou</caption>
                            <thead><tr><th scope="col">Código</th><th scope="col">Plano</th><th scope="col">Situação</th><th scope="col">Validade</th></tr></thead>
                            <tbody>
                            @foreach ($gifts as $g)
                                @php($state = $g->effectiveStatus())
                                <tr>
                                    <td data-label="Código"><code>{{ $g->maskedCode() }}</code></td>
                                    <td data-label="Plano">{{ $g->plan?->name }}</td>
                                    <td data-label="Situação">
                                        <span>
                                            <span @class(['badge', 'badge-success' => $state === \App\Enums\GiftCodeStatus::Available, 'badge-muted' => $state !== \App\Enums\GiftCodeStatus::Available])>{{ $state->getLabel() }}</span>
                                            @if ($state === \App\Enums\GiftCodeStatus::Redeemed && $g->redeemed_at)<br><span class="hint">em {{ $g->redeemed_at->format('d/m/Y') }}</span>@endif
                                        </span>
                                    </td>
                                    <td data-label="Validade">{{ $g->expires_at->format('d/m/Y') }}</td>
                                </tr>
                                @if ($state === \App\Enums\GiftCodeStatus::Available)
                                    <tr>
                                        <td colspan="4" class="full">
                                            <details class="inline-details">
                                                <summary>Perdeu o código? Gerar um novo e enviar por e-mail</summary>
                                                <form class="form mt-1" method="post" action="{{ route('account.gifts.regenerate', $g->uuid) }}">
                                                    @csrf
                                                    <p class="soft small mb-0">Por segurança, não guardamos o código. Geramos um novo e enviamos só para <strong>{{ $user->email }}</strong>.</p>
                                                    <label class="check"><input type="checkbox" name="confirm" value="1" required> <span>Entendo que o código anterior deixará de funcionar.</span></label>
                                                    <div><button class="btn btn-outline btn-sm" type="submit"><x-sancta-icon name="refresh" /> Gerar novo código</button></div>
                                                </form>
                                            </details>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div>
            <form class="card form" id="perfil" method="post" action="{{ route('account.profile') }}">
                @csrf
                <h2 class="card-title">Perfil e aparência</h2>
                <label for="nome">Nome<input id="nome" type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" autocomplete="name"></label>
                <label for="email-conta">E-mail<input id="email-conta" type="email" value="{{ $user->email }}" disabled></label>
                <label for="tema">Tema preferido
                    <select id="tema" name="theme">
                        @foreach (\App\Enums\ThemePreference::cases() as $t)
                            <option value="{{ $t->value }}" @selected($user->theme === $t)>{{ $t->getLabel() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="check"><input type="checkbox" name="marketing_opt_in" value="1" @checked($user->marketing_opt_in)> <span>Receber novidades por e-mail</span></label>
                <div><button class="btn btn-primary" type="submit">Salvar</button></div>
            </form>

            <form class="card form" id="seguranca" method="post" action="{{ route('account.password') }}">
                @csrf
                <h2 class="card-title">{{ $user->password ? 'Alterar senha' : 'Criar senha' }}</h2>
                @if ($user->password)
                    <label for="senha-atual">Senha atual<input id="senha-atual" type="password" name="current_password" autocomplete="current-password" required></label>
                @endif
                <label for="senha-nova">Nova senha<input id="senha-nova" type="password" name="password" autocomplete="new-password" required minlength="8"><span class="hint">Mínimo de 8 caracteres, com letras e números.</span></label>
                <label for="senha-confirma">Confirme<input id="senha-confirma" type="password" name="password_confirmation" autocomplete="new-password" required></label>
                <div><button class="btn btn-outline" type="submit">Salvar senha</button></div>
            </form>

            <details class="card danger-zone">
                <summary>Excluir minha conta <x-sancta-icon name="chevron-down" /></summary>
                <form class="form mt-2" method="post" action="{{ route('account.destroy') }}">
                    @csrf
                    <p class="soft small mb-0">Esta ação é permanente: seus dados pessoais, anotações e favoritos serão apagados. Assinaturas do Google Play devem ser canceladas também na Play Store.</p>
                    @if ($user->password)
                        <label for="senha-excluir">Senha<input id="senha-excluir" type="password" name="password" autocomplete="current-password" required></label>
                    @endif
                    <label for="confirma-excluir">Digite <strong>EXCLUIR</strong> para confirmar<input id="confirma-excluir" type="text" name="confirmation" required autocomplete="off"></label>
                    <div><button class="btn btn-danger" type="submit">Excluir definitivamente</button></div>
                </form>
            </details>
        </div>
    </div>
</section>
@endsection
