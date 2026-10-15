<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use Filament\Widgets\ChartWidget;

class PaymentMethodsChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Pagamentos aprovados por forma (90 dias)';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasRoleAtLeast(UserRole::Admin);
    }

    protected function getData(): array
    {
        $groups = Payment::query()
            ->where('status', PaymentStatus::Approved->value)
            ->where('paid_at', '>=', now()->subDays(90))
            ->get(['method'])
            ->groupBy(fn (Payment $p) => $p->method->getLabel())
            ->map->count();

        return [
            'datasets' => [[
                'data' => $groups->values()->all(),
                'backgroundColor' => ['#1F3D9C', '#9DB2FF', '#E3B65B', '#5A6487'],
            ]],
            'labels' => $groups->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
