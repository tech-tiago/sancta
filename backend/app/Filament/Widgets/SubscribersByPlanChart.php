<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use Filament\Widgets\ChartWidget;

class SubscribersByPlanChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Assinantes ativos por plano';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $groups = Subscription::query()->entitled()->with('plan')->get()
            ->groupBy(fn (Subscription $s) => $s->plan?->name ?? 'Cortesia')
            ->map->count();

        return [
            'datasets' => [[
                'data' => $groups->values()->all(),
                'backgroundColor' => ['#1F3D9C', '#E3B65B', '#9DB2FF', '#132760', '#5A6487', '#C8D5FF'],
            ]],
            'labels' => $groups->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
