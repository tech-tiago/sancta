<?php

namespace Tests\Feature\Security;

use App\Enums\GiftCodeStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\GiftCode;
use App\Models\User;
use App\Notifications\GiftCodeIssued;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\Gateways\GatewayPayment;
use App\Services\Billing\GiftCodeException;
use App\Services\Billing\GiftService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Pentest fase 3 — SEC-14 (reforço): corrida no resgate do presente.
 *
 * No MySQL o resgate já era serializado pela trava de linha (SELECT … FOR UPDATE). A garantia,
 * porém, dependia só do banco: onde a trava não vale (SQLite, réplica, mudança futura de consulta)
 * duas transações que leem o código "disponível" ao mesmo tempo concediam o período duas vezes.
 * Simulação: a transação 1 lê o código; a transação 2 resgata e confirma; a transação 1 continua.
 * Agora a baixa do código é uma troca atômica condicional (status = disponível): só uma vence.
 */
class GiftRedeemRaceTest extends TestCase
{
    use RefreshDatabase;

    private function issueCode(User $buyer): string
    {
        Notification::fake();
        $plan = $this->makePlan(['price_cents' => 14990]);
        $checkout = app(CheckoutService::class);
        $payment = $checkout->start($buyer, $plan, PaymentMethod::Pix, null, null, true);
        $checkout->apply($payment, new GatewayPayment((string) $payment->provider_payment_id, PaymentStatus::Approved, $payment->amount_cents, $payment->uuid));

        $code = null;
        Notification::assertSentTo($buyer, GiftCodeIssued::class, function (GiftCodeIssued $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        return $code;
    }

    public function test_interleaved_redemptions_grant_the_period_only_once(): void
    {
        $code = $this->issueCode($this->makeUser());
        [$late, $winner] = [$this->makeUser(), $this->makeUser()];
        $gift = GiftCode::query()->sole();

        // Logo depois que a transação de $late lê o código, outra transação ($winner) resgata e confirma.
        $interleaved = false;
        Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$interleaved, $gift, $winner): void {
            if ($interleaved || ! str_contains($query->sql, 'from "gift_codes"') || ! str_contains($query->sql, '"code_hash" in')) {
                return;
            }
            $interleaved = true;
            DB::table('gift_codes')->where('id', $gift->id)->update([
                'status' => GiftCodeStatus::Redeemed->value, 'redeemed_by' => $winner->id, 'redeemed_at' => now(),
            ]);
        });

        try {
            app(GiftService::class)->redeem($late, $code);
            $lateResult = 'ok';
        } catch (GiftCodeException) {
            $lateResult = 'recusado';
        }

        $this->assertTrue($interleaved, 'A intercalação foi simulada');
        $this->assertSame('recusado', $lateResult, 'Quem chegou depois não pode resgatar o mesmo código');
        $this->assertSame($winner->id, (int) $gift->fresh()->redeemed_by, 'O resgate confirmado não é sobrescrito');
        $this->assertSame(0, $late->subscriptions()->count());
        $this->assertFalse($late->fresh()->isPremium());
    }
}
