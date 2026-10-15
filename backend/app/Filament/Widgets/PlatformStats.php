<?php

namespace App\Filament\Widgets;

use App\Enums\ModerationStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\ChurchSubmission;
use App\Models\Payment;
use App\Models\PrayerIntention;
use App\Models\PrayerSession;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $usersTotal = User::query()->count();
        $usersNew = User::query()->where('created_at', '>=', now()->subDays(30))->count();
        $signups = collect(range(13, 0))->map(fn ($d) => User::query()->whereDate('created_at', now()->subDays($d)->toDateString())->count())->all();

        $subscribers = Subscription::query()->entitled()->distinct('user_id')->count('user_id');

        $paid = fn ($from, $to) => (int) Payment::query()
            ->where('status', PaymentStatus::Approved->value)
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount_cents');
        $revenueMonth = $paid(now()->startOfMonth(), now());
        $revenuePrev = $paid(now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow());
        $trend = $revenuePrev > 0 ? round(($revenueMonth - $revenuePrev) / $revenuePrev * 100) : null;

        $mrr = Subscription::query()->entitled()
            ->whereIn('provider', [PaymentProvider::GooglePlay->value, PaymentProvider::MercadoPago->value])
            ->with('plan')->get()
            ->sum(fn (Subscription $s) => $s->plan?->monthlyEquivalentCents() ?? 0);

        $pending = ChurchSubmission::query()->where('status', ModerationStatus::Pending->value)->count()
            + PrayerIntention::query()->where('status', ModerationStatus::Pending->value)->count();

        $prayingToday = PrayerSession::query()->whereDate('prayed_on', today())->distinct('user_id')->count('user_id');

        $finance = (bool) auth()->user()?->hasRoleAtLeast(UserRole::Admin);

        return array_values(array_filter([
            Stat::make('Usuários', number_format($usersTotal, 0, ',', '.'))
                ->description("+{$usersNew} nos últimos 30 dias")
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->chart($signups)
                ->color('primary'),
            Stat::make('Assinantes Sancta+', number_format($subscribers, 0, ',', '.'))
                ->description($usersTotal ? round($subscribers / $usersTotal * 100, 1).'% de conversão' : '—')
                ->descriptionIcon(Heroicon::OutlinedStar)
                ->color('success'),
            $finance ? Stat::make('Receita do mês', Money::format($revenueMonth))
                ->description($trend === null ? 'Mês anterior: '.Money::format($revenuePrev) : ($trend >= 0 ? "+{$trend}%" : "{$trend}%").' vs. mês anterior')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color($trend !== null && $trend < 0 ? 'danger' : 'success') : null,
            $finance ? Stat::make('Receita recorrente (MRR)', Money::format($mrr))
                ->description('Estimativa mensal das assinaturas pagas ativas')
                ->descriptionIcon(Heroicon::OutlinedChartBar) : null,
            Stat::make('Rezando hoje', number_format($prayingToday, 0, ',', '.'))
                ->description('Usuários que registraram oração hoje')
                ->descriptionIcon(Heroicon::OutlinedFire)
                ->color('warning'),
            Stat::make('Moderação pendente', (string) $pending)
                ->description('Sugestões de igrejas + intenções')
                ->descriptionIcon(Heroicon::OutlinedInboxArrowDown)
                ->color($pending > 0 ? 'warning' : 'gray'),
        ]));
    }
}
