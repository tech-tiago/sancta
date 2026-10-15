<?php

namespace Tests\Feature\Security;

use App\Enums\GiftCodeStatus;
use App\Models\GiftCode;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\GiftCodeIssued;
use App\Services\Billing\GiftService;
use App\Support\GiftCodeFormat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * Pentest fase 3 — vazamento do código de presente (texto em claro).
 *
 *  - SEC-12: a exibição única na página do pagamento valia para QUALQUER sessão da conta
 *    (outro aparelho, computador compartilhado ou sessão sequestrada lia o código antes do comprador).
 *    Agora só o navegador que iniciou a compra vê o código; as demais sessões são orientadas a usar o e-mail.
 *  - SEC-13: com o e-mail em modo "registro" (MAIL_MAILER=log, padrão do .env.example) fora do ambiente
 *    local, o e-mail com o código era gravado no arquivo de log. Agora o código nunca é entregue por um
 *    transporte que não envia de verdade (log/array) fora de local/testes — fica só a exibição única.
 *  - Também conferido: código fora da fila (jobs), do cache em claro, da sessão, da auditoria e com
 *    "Cache-Control: no-store" na página.
 */
class GiftCodeLeakTest extends TestCase
{
    use RefreshDatabase;

    private function buyGift(User $buyer): array
    {
        $plan = $this->makePlan(['slug' => 'anual', 'price_cents' => 14990]);

        $this->actingAs($buyer)->post("/assinar/{$plan->slug}", ['method' => 'pix', 'gift' => '1', 'accept_terms' => '1'])->assertRedirect();
        $payment = Payment::query()->where('user_id', $buyer->id)->latest('id')->firstOrFail();
        $this->actingAs($buyer)->post("/pagamento/{$payment->uuid}/simular", ['result' => 'approve'])->assertRedirect();

        $code = null;
        Notification::assertSentTo($buyer, GiftCodeIssued::class, function (GiftCodeIssued $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        return [$payment->fresh(), $code];
    }

    public function test_one_time_code_is_only_shown_to_the_browser_that_bought_it(): void
    {
        Notification::fake();
        $buyer = $this->makeUser();
        [$payment, $code] = $this->buyGift($buyer);
        $buyingSession = session()->all();

        // Outra sessão da mesma conta (outro aparelho / sessão roubada): não vê e não "gasta" a exibição.
        $this->flushSession();
        $this->actingAs($buyer)->get("/pagamento/{$payment->uuid}")
            ->assertOk()
            ->assertDontSee($code)
            ->assertSee('Enviamos o código do presente');

        // O navegador da compra ainda vê o código — uma única vez.
        $this->flushSession();
        $this->withSession($buyingSession)->actingAs($buyer)->get("/pagamento/{$payment->uuid}")
            ->assertOk()
            ->assertSee($code)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($buyer)->get("/pagamento/{$payment->uuid}")->assertOk()->assertDontSee($code);
    }

    public function test_code_is_never_stored_in_clear_in_queue_cache_session_or_audit(): void
    {
        // Fila e cache em banco (como em produção) para inspecionar o que foi gravado.
        config(['queue.default' => 'database', 'cache.default' => 'database']);
        app('queue')->setDefaultDriver('database');
        app('cache')->setDefaultDriver('database');

        Notification::fake();
        $buyer = $this->makeUser();
        [$payment, $code] = $this->buyGift($buyer);
        $canonical = (string) GiftCodeFormat::canonical($code);
        $this->assertSame(1, DB::table('cache')->where('key', 'like', '%gift-reveal:'.$payment->uuid)->count(), 'Cópia de exibição única existe (criptografada)');

        $haystacks = [
            'jobs' => DB::table('jobs')->get()->toJson(),
            'cache' => DB::table('cache')->get()->toJson(),
            'audit' => DB::table('audit_logs')->get()->toJson(),
            'session' => json_encode(session()->all()),
            'payments' => DB::table('payments')->get()->toJson(),
            'gift_codes' => DB::table('gift_codes')->get()->toJson(),
        ];

        foreach ($haystacks as $where => $content) {
            $this->assertStringNotContainsString($canonical, $content, "Código em claro em: {$where}");
            $this->assertStringNotContainsString(substr($canonical, 0, 8), $content, "Parte do código em: {$where}");
        }
        $this->assertNotInstanceOf(ShouldQueue::class, new GiftCodeIssued($code, 'Anual', now()));
    }

    public function test_code_is_not_written_to_the_log_by_the_log_mailer_outside_local(): void
    {
        $buyer = $this->makeUser();
        $plan = $this->makePlan(['price_cents' => 14990]);
        $payment = new Payment;
        $payment->forceFill([
            'user_id' => $buyer->id, 'plan_id' => $plan->id, 'provider' => 'fake', 'method' => 'pix',
            'status' => 'approved', 'amount_cents' => 14990, 'is_gift' => true,
        ])->save();

        config(['mail.default' => 'log', 'mail.mailers.log.transport' => 'log']);
        $this->app['env'] = 'production';
        $logged = [];
        Log::listen(function ($event) use (&$logged): void {
            $logged[] = $event->message.' '.json_encode($event->context);
        });
        Mail::purge('log');

        app(GiftService::class)->issueForPayment($payment);
        $this->app['env'] = 'testing';

        $plain = app(GiftService::class)->revealOnce($payment);
        $this->assertNotNull($plain, 'O comprador ainda vê o código uma vez na página do pagamento');
        $canonical = (string) GiftCodeFormat::canonical($plain);

        $all = implode("\n", $logged);
        $this->assertStringNotContainsString($canonical, str_replace('-', '', $all), 'Código em claro no log');
        $this->assertStringContainsString('não foi enviado', $all, 'Aviso operacional (sem o código) no log');
    }

    public function test_new_code_is_refused_while_email_only_goes_to_the_log(): void
    {
        Notification::fake();
        $buyer = $this->makeUser();
        [, $code] = $this->buyGift($buyer);
        $gift = GiftCode::query()->sole();

        config(['mail.default' => 'log']);
        $this->app['env'] = 'production';
        try {
            app(GiftService::class)->regenerate($gift, $buyer);
            $this->fail('Sem e-mail de verdade, o novo código se perderia.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('e-mails', $e->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame($gift->code_hash, $gift->fresh()->code_hash, 'O código anterior continua valendo');
        $this->assertSame(GiftCodeStatus::Redeemed, app(GiftService::class)->redeem($this->makeUser(), $code)->status);
    }
}
