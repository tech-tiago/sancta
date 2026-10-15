<?php

namespace App\Services\Billing;

use App\Enums\GiftCodeStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\GiftCode;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\GiftCodeIssued;
use App\Support\GiftCodeFormat;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * "Presentear Sancta+" (somente no site — política do Google Play).
 *
 * Decisões de segurança:
 *  - O código em claro só existe na criação: vai por e-mail ao comprador e é exibido
 *    UMA vez na página do pagamento aprovado (cópia criptografada no cache por até 60 min,
 *    apagada na primeira exibição). No banco: HMAC-SHA256 (APP_KEY) + 4 últimos caracteres.
 *  - Para "rever" o código, o comprador gera um NOVO código em Minha conta → Presentes:
 *    o novo vai só para o e-mail dele e o anterior deixa de valer (nada recuperável do banco).
 *  - A exibição única só acontece no navegador que fez a compra (CheckoutController) e o código nunca
 *    é "enviado" por transporte de e-mail que só grava em log (fora de local/testes).
 *  - Resgate em transação com lockForUpdate + baixa condicional atômica; mensagens genéricas (anti-enumeração).
 *  - Estorno/chargeback: código disponível → revogado; já resgatado → período do resgatador revogado.
 */
class GiftService
{
    public const REVEAL_TTL_MINUTES = 60;

    public function __construct(private readonly EntitlementService $entitlements) {}

    /**
     * Gera o código de um pagamento de presente APROVADO. Idempotente (um código por pagamento).
     * Chamado dentro da transação de CheckoutService::apply (pagamento já travado).
     */
    public function issueForPayment(Payment $payment): GiftCode
    {
        $existing = GiftCode::query()->where('payment_id', $payment->getKey())->first();
        if ($existing) {
            return $existing;
        }

        if (! $payment->is_gift || $payment->status !== PaymentStatus::Approved) {
            throw new LogicException('Somente pagamentos de presente aprovados geram código.');
        }

        [$plain, $canonical] = $this->newCode();

        $gift = new GiftCode;
        $gift->forceFill([
            'code_hash' => GiftCodeFormat::hash($canonical),
            'last4' => substr($canonical, -4),
            'plan_id' => $payment->plan_id,
            'purchaser_id' => $payment->user_id,
            'payment_id' => $payment->getKey(),
            'status' => GiftCodeStatus::Available,
            'expires_at' => now()->addMonthsNoOverflow(GiftCode::VALID_MONTHS),
        ])->save();

        if ($payment->coupon_id) {
            $payment->coupon()->increment('redemptions_count');
        }

        $paymentUuid = $payment->uuid;
        DB::afterCommit(function () use ($gift, $plain, $paymentUuid): void {
            // Exibição única na página do pagamento (criptografado, curta duração).
            Cache::put($this->revealKey($paymentUuid), Crypt::encryptString($plain), now()->addMinutes(self::REVEAL_TTL_MINUTES));
            $this->sendCode($gift, $plain, regenerated: false);
        });

        return $gift;
    }

    /** Código em claro para a página do pagamento — devolve uma única vez (depois, só por e-mail). */
    public function revealOnce(Payment $payment): ?string
    {
        $encrypted = Cache::pull($this->revealKey($payment->uuid));
        if (! is_string($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Resgata um código para $user (soma o período ao acesso atual).
     *
     * @throws GiftCodeException sempre com a mesma mensagem genérica
     */
    public function redeem(User $user, string $input): GiftCode
    {
        $canonical = GiftCodeFormat::canonical($input);
        if ($canonical === null) {
            throw GiftCodeException::generic();
        }

        $hashes = GiftCodeFormat::hashCandidates($canonical);

        $gift = DB::transaction(function () use ($hashes, $user): ?GiftCode {
            /** @var GiftCode|null $gift */
            $gift = GiftCode::query()->whereIn('code_hash', $hashes)->lockForUpdate()->first();
            if (! $gift) {
                return null;
            }

            if ($gift->status === GiftCodeStatus::Available && $gift->expires_at->isPast()) {
                $gift->forceFill(['status' => GiftCodeStatus::Expired])->save();

                return null;
            }

            if ($gift->status !== GiftCodeStatus::Available) {
                return null;
            }

            // Defesa extra: o pagamento precisa continuar aprovado.
            $paymentStatus = Payment::query()->whereKey($gift->payment_id)->value('status');
            if ($paymentStatus !== PaymentStatus::Approved) {
                return null;
            }

            // Baixa atômica e condicional (só vale se o código ainda estiver disponível e for o mesmo):
            // a garantia de uso único não depende só da trava de linha do banco. Pentest fase 3 — SEC-14.
            $redeemedAt = now();
            $claimed = GiftCode::query()
                ->whereKey($gift->getKey())
                ->where('status', GiftCodeStatus::Available->value)
                ->where('code_hash', $gift->code_hash)
                ->update(['status' => GiftCodeStatus::Redeemed->value, 'redeemed_by' => $user->getKey(), 'redeemed_at' => $redeemedAt, 'updated_at' => $redeemedAt]);
            if ($claimed !== 1) {
                return null;
            }

            $subscription = $this->entitlements->grantFromGift($gift, $user);

            $gift->forceFill([
                'status' => GiftCodeStatus::Redeemed,
                'redeemed_by' => $user->getKey(),
                'redeemed_at' => $redeemedAt,
                'subscription_id' => $subscription->getKey(),
            ])->save();

            return $gift;
        });

        if (! $gift) {
            throw GiftCodeException::generic();
        }

        Log::info('Presente Sancta+ resgatado', ['gift' => $gift->uuid, 'user' => $user->uuid]);

        return $gift;
    }

    /** Estorno/chargeback do pagamento do presente (chamado por CheckoutService::apply). */
    public function revokeForPayment(Payment $payment): void
    {
        $gift = GiftCode::query()->where('payment_id', $payment->getKey())->first();
        if ($gift) {
            $this->revoke($gift, 'Pagamento '.mb_strtolower($payment->status->getLabel()));
        }
    }

    /**
     * Revoga o código. Se já foi resgatado, encerra também o período concedido ao resgatador.
     * Com $admin, registra na auditoria (ação do painel).
     */
    public function revoke(GiftCode $gift, string $reason, ?User $admin = null): GiftCode
    {
        $before = null;

        $gift = DB::transaction(function () use ($gift, $reason, &$before): GiftCode {
            /** @var GiftCode $gift */
            $gift = GiftCode::query()->whereKey($gift->getKey())->lockForUpdate()->firstOrFail();
            $before = $gift->status;

            if (! $gift->isRevocable()) {
                return $gift;
            }

            if ($gift->status === GiftCodeStatus::Redeemed && $gift->subscription) {
                $this->entitlements->revokeGiftSubscription($gift->subscription, 'Presente revogado: '.$reason);
            }

            $gift->forceFill([
                'status' => GiftCodeStatus::Revoked,
                'revoked_at' => now(),
                'revoked_reason' => mb_substr($reason, 0, 255),
            ])->save();

            return $gift;
        });

        if ($admin && $before !== GiftCodeStatus::Revoked) {
            AuditLog::record('gift_code.revoked', $gift, ['status' => $before?->value], ['status' => $gift->status->value, 'motivo' => $reason], $admin);
        }

        return $gift;
    }

    /**
     * Gera um novo código para um presente ainda disponível e envia SÓ para o e-mail do comprador
     * (o anterior deixa de valer). É assim que o comprador "revê" o código.
     *
     * @throws RuntimeException quando o presente não pode mais ser alterado
     */
    public function regenerate(GiftCode $gift, User $owner): GiftCode
    {
        if ((int) $gift->purchaser_id !== (int) $owner->getKey()) {
            throw new LogicException('Somente o comprador pode gerar um novo código.');
        }

        // Sem e-mail de verdade o novo código se perderia (e o anterior já teria deixado de valer).
        if (! $this->emailDelivers()) {
            throw new RuntimeException('O envio de e-mails está indisponível no momento. Tente novamente mais tarde.');
        }

        $plain = null;

        $gift = DB::transaction(function () use ($gift, &$plain): GiftCode {
            /** @var GiftCode $gift */
            $gift = GiftCode::query()->whereKey($gift->getKey())->lockForUpdate()->firstOrFail();

            $paymentStatus = Payment::query()->whereKey($gift->payment_id)->value('status');
            if (! $gift->isRedeemable() || $paymentStatus !== PaymentStatus::Approved) {
                throw new RuntimeException('Este presente não está mais disponível para gerar um novo código.');
            }

            [$plain, $canonical] = $this->newCode();
            $gift->forceFill(['code_hash' => GiftCodeFormat::hash($canonical), 'last4' => substr($canonical, -4)])->save();

            return $gift;
        });

        $this->sendCode($gift, (string) $plain, regenerated: true);
        AuditLog::record('gift_code.regenerated', $gift, [], ['last4' => $gift->last4], $owner);

        return $gift;
    }

    /** @return array{0: string, 1: string} [código formatado, canônico] */
    private function newCode(): array
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $plain = GiftCodeFormat::generate();
            $canonical = (string) GiftCodeFormat::canonical($plain);
            if (! GiftCode::query()->where('code_hash', GiftCodeFormat::hash($canonical))->exists()) {
                return [$plain, $canonical];
            }
        }

        throw new RuntimeException('Não foi possível gerar um código único.');
    }

    private function sendCode(GiftCode $gift, string $plain, bool $regenerated): void
    {
        if (! $this->emailDelivers()) {
            // Transporte "log"/"array" gravaria o e-mail (com o código em claro) no log. Pentest fase 3 — SEC-13.
            Log::warning('Código de presente não foi enviado por e-mail: o envio está em modo registro (log). Configure o SMTP.', ['gift' => $gift->uuid]);

            return;
        }

        try {
            $gift->purchaser?->notify(new GiftCodeIssued(
                $plain,
                (string) ($gift->plan?->name ?? ''),
                $gift->expires_at,
                $regenerated,
            ));
        } catch (Throwable $e) {
            // Falha de e-mail não desfaz o presente: o comprador pode gerar um novo código depois.
            report($e);
        }
    }

    /**
     * O e-mail sai de verdade? Fora de local/testes, os transportes "log" e "array" não entregam nada
     * e o "log" grava a mensagem inteira (com o código) no arquivo de log.
     */
    private function emailDelivers(): bool
    {
        if (app()->environment(['local', 'testing'])) {
            return true;
        }

        $transport = (string) config('mail.mailers.'.config('mail.default').'.transport');

        return ! in_array($transport, ['log', 'array'], true);
    }

    private function revealKey(string $paymentUuid): string
    {
        return 'gift-reveal:'.$paymentUuid;
    }
}
