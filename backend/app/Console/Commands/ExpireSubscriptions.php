<?php

namespace App\Console\Commands;

use App\Enums\GiftCodeStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\GiftCode;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Push\PushService;
use Illuminate\Console\Command;

/**
 * Rotina diária de assinaturas:
 *  - marca como expiradas as que passaram da data de término;
 *  - expira cobranças Pix/cartão abandonadas;
 *  - avisa (push) quem tem plano do site vencendo em 3 dias.
 */
class ExpireSubscriptions extends Command
{
    protected $signature = 'sancta:subscriptions-maintenance';

    protected $description = 'Expira assinaturas vencidas, cobranças abandonadas e envia lembretes de renovação';

    public function handle(PushService $push): int
    {
        $expired = Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value, SubscriptionStatus::Canceled->value, SubscriptionStatus::PastDue->value])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now()->subDay()) // 1 dia de tolerância (Google Play pode atrasar o RTDN)
            ->update(['status' => SubscriptionStatus::Expired->value]);

        $abandoned = Payment::query()
            ->where('status', PaymentStatus::Pending->value)
            ->where('expires_at', '<', now()->subHour())
            ->update(['status' => PaymentStatus::Expired->value]);

        // Códigos de presente não resgatados dentro da validade (12 meses).
        GiftCode::query()
            ->where('status', GiftCodeStatus::Available->value)
            ->where('expires_at', '<', now())
            ->update(['status' => GiftCodeStatus::Expired->value]);

        $reminded = 0;
        Subscription::query()->entitled()
            ->where('auto_renew', false)
            ->whereIn('provider', ['mercadopago', 'manual'])
            ->whereBetween('ends_at', [now()->addDays(3)->startOfDay(), now()->addDays(3)->endOfDay()])
            ->with('user')
            ->each(function (Subscription $s) use ($push, &$reminded): void {
                if ($s->user) {
                    $push->notifyUser($s->user, 'Seu Sancta+ vence em 3 dias', 'Renove para continuar com orações guiadas, jornadas e reflexões.', 'sancta://planos');
                    $reminded++;
                }
            });

        $this->info("Assinaturas expiradas: {$expired} · Cobranças expiradas: {$abandoned} · Lembretes: {$reminded}");

        return self::SUCCESS;
    }
}
