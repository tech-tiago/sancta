<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Receita — últimos 12 meses (R$)';

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 2];

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasRoleAtLeast(UserRole::Admin);
    }

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonthsNoOverflow($i));

        $values = $months->map(fn ($month) => round(Payment::query()
            ->where('status', PaymentStatus::Approved->value)
            ->whereBetween('paid_at', [$month, $month->copy()->endOfMonth()])
            ->sum('amount_cents') / 100, 2));

        return [
            'datasets' => [[
                'label' => 'Receita',
                'data' => $values->all(),
                'borderColor' => '#1F3D9C',
                'backgroundColor' => 'rgba(31,61,156,0.12)',
                'fill' => true,
                'tension' => 0.35,
            ]],
            'labels' => $months->map(fn ($m) => $m->translatedFormat('M/y'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
