@extends('layouts.site', ['title' => 'Pagamento'])

@php
    $qr = $payment->pix_qr_code_base64;
    $qrSrc = $qr ? (str_starts_with($qr, 'svg:') ? 'data:image/svg+xml;base64,'.substr($qr, 4) : 'data:image/png;base64,'.$qr) : null;
    $premiumName = app(\App\Services\Settings::class)->get('premium.name');
    $status = $payment->status->value;
    $isPixWaiting = $payment->isPending() && $payment->method->value === 'pix' && $payment->pix_qr_code;
    $badgeClass = match (true) {
        $status === 'approved' => 'badge-success',
        $payment->isPending() => '',
        default => 'badge-danger',
    };
@endphp

@section('content')
<section class="section-tight section-last">
    <div class="container container-narrow">
        <article class="card card-elevated pay-card" id="payment" data-status="{{ $status }}" aria-labelledby="pagamento-titulo">
            <header class="pay-head">
                <div>
                    <span class="eyebrow">@if ($payment->is_gift)<x-sancta-icon name="gift" /> Presente · @endif{{ $premiumName }} {{ $payment->plan?->name }}</span>
                    <p class="pay-amount" id="pagamento-titulo">{{ $payment->formattedAmount() }}</p>
                </div>
                <span class="badge {{ $badgeClass }}" id="status-label" role="status">@if ($payment->isPending())<span class="spinner" aria-hidden="true"></span>@endif{{ $payment->status->getLabel() }}</span>
            </header>

            @if ($status === 'approved' && $payment->is_gift)
                {{-- Presente: o código em claro aparece UMA vez; depois, só no e-mail (ou gerando um novo). --}}
                <div class="pay-result">
                    <span class="status-icon ok"><x-sancta-icon name="gift" /></span>
                    <h1 class="pay-title">Pagamento aprovado! Seu presente está pronto.</h1>
                </div>
                @if (! empty($giftCode))
                    <p class="soft">Este é o código do presente. Copie e envie para quem vai recebê-lo:</p>
                    <div class="gift-code" id="gift-code">{{ $giftCode }}</div>
                    <div class="actions mt-2"><button class="btn btn-outline btn-block" type="button" data-copy="gift-code"><x-sancta-icon name="copy" /><span data-copy-label>Copiar código</span></button></div>
                    <div class="alert alert-info mt-2"><x-sancta-icon name="lock" /><div>Por segurança, o código não será exibido novamente nesta página. Também enviamos para <strong>{{ auth()->user()->email }}</strong>.</div></div>
                @else
                    <p class="soft">Enviamos o código do presente para <strong>{{ auth()->user()->email }}</strong>@if ($gift) (final <strong>{{ $gift->last4 }}</strong>)@endif. Se não encontrar o e-mail, você pode gerar um novo código em Minha conta → Presentes.</p>
                @endif
                <p class="soft">Quem receber deve entrar no site e informar o código em <a href="{{ route('gift.redeem') }}">{{ route('gift.redeem') }}</a>. O código vale por 12 meses.</p>
                <div class="actions mt-2"><a class="btn btn-primary" href="{{ route('account.show') }}#presentes">Ver meus presentes</a></div>
            @elseif ($status === 'approved')
                <div class="pay-result">
                    <span class="status-icon ok"><x-sancta-icon name="check-circle" /></span>
                    <h1 class="pay-title">Pagamento aprovado</h1>
                    <p class="soft">Seu acesso ao {{ $premiumName }} já está liberado no app e no site, com a mesma conta. Que seja um tempo bonito de oração.</p>
                </div>
                <div class="actions"><a class="btn btn-primary" href="{{ route('account.show') }}">Ir para minha conta</a><x-store-badge /></div>
            @elseif ($isPixWaiting)
                <h1 class="pay-title">Pague com Pix</h1>
                <div class="pay-grid mt-2">
                    @if ($qrSrc)
                        <div class="qr-frame"><img src="{{ $qrSrc }}" alt="QR Code Pix para pagar {{ $payment->formattedAmount() }}" width="248" height="248"></div>
                    @endif
                    <div>
                        <ol class="number-list">
                            <li><span>Abra o app do seu banco e escolha <strong>Pix → Ler QR Code</strong>.</span></li>
                            <li><span>Ou use o código <strong>copia e cola</strong> abaixo.</span></li>
                            <li><span>A confirmação aparece aqui automaticamente.</span></li>
                        </ol>
                        <div class="code-box mt-2" id="pix-code">{{ $payment->pix_qr_code }}</div>
                        <button class="btn btn-primary btn-block mt-2" type="button" data-copy="pix-code"><x-sancta-icon name="copy" /><span data-copy-label>Copiar código Pix</span></button>
                        <p class="hint mt-2 mb-0 pay-wait"><span class="spinner" aria-hidden="true"></span> Aguardando pagamento… válido até {{ $payment->expires_at?->format('H:i') }}.</p>
                    </div>
                </div>
            @elseif ($payment->isPending())
                <div class="pay-result">
                    <span class="status-icon wait"><span class="spinner" aria-hidden="true"></span></span>
                    <h1 class="pay-title">Quase lá</h1>
                    <p class="soft">Estamos aguardando a confirmação do Mercado Pago. Isso costuma levar poucos segundos — esta página se atualiza sozinha.</p>
                </div>
                @if ($payment->checkout_url && ! request()->boolean('simulado'))
                    <div class="actions"><a class="btn btn-primary" href="{{ $payment->checkout_url }}"><x-sancta-icon name="card" /> Voltar ao pagamento com cartão</a></div>
                @endif
            @else
                <div class="pay-result">
                    <span class="status-icon fail"><x-sancta-icon name="x-circle" /></span>
                    <h1 class="pay-title">Pagamento não concluído</h1>
                </div>
                <div class="alert alert-error" role="alert"><x-sancta-icon name="alert" /><div>Este pagamento está <strong>{{ mb_strtolower($payment->status->getLabel()) }}</strong>. {{ $payment->failure_reason }}</div></div>
                <div class="actions"><a class="btn btn-primary" href="{{ route('checkout.show', $payment->plan) }}"><x-sancta-icon name="refresh" /> Tentar novamente</a></div>
            @endif

            @if ($canSimulate && $payment->isPending())
                <div class="alert alert-dashed mt-3"><x-sancta-icon name="info" />
                    <div>
                        <strong>Ambiente de demonstração</strong> — nenhuma cobrança real. Simule o resultado:
                        <div class="actions mt-1">
                            <form method="post" action="{{ route('checkout.simulate', $payment->uuid) }}">@csrf<input type="hidden" name="result" value="approve"><button class="btn btn-primary btn-sm">Aprovar pagamento</button></form>
                            <form method="post" action="{{ route('checkout.simulate', $payment->uuid) }}">@csrf<input type="hidden" name="result" value="reject"><button class="btn btn-outline btn-sm">Recusar</button></form>
                        </div>
                    </div>
                </div>
            @endif
        </article>
        <p class="hint center mt-2"><x-sancta-icon name="shield" class="icon-inline" /> Pagamento processado pelo Mercado Pago. Dúvidas? <a href="mailto:{{ app(\App\Services\Settings::class)->get('app.support_email') }}">Fale com a gente</a>.</p>
    </div>
</section>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce }}">
(function () {
    // Atualiza a página quando o pagamento sai de "pendente" (consulta leve a cada 5 s, até 10 min).
    var box = document.getElementById('payment');
    if (!box || box.dataset.status !== 'pending') return;
    var tries = 0;
    var timer = setInterval(function () {
        if (++tries > 120) return clearInterval(timer);
        fetch(@json(route('checkout.poll', $payment->uuid)), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d.status !== 'pending') { clearInterval(timer); location.reload(); } })
            .catch(function () {});
    }, 5000);
})();
</script>
@endpush
