<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class SignupsChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Novos usuários — 30 dias';

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 2];

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn ($d) => now()->subDays($d)->startOfDay());
        $counts = User::query()
            ->where('created_at', '>=', $days->first())
            ->get(['created_at'])
            ->groupBy(fn ($u) => $u->created_at->toDateString())
            ->map->count();

        return [
            'datasets' => [[
                'label' => 'Cadastros',
                'data' => $days->map(fn ($d) => $counts[$d->toDateString()] ?? 0)->all(),
                'backgroundColor' => '#1F3D9C',
                'borderRadius' => 4,
            ]],
            'labels' => $days->map(fn ($d) => $d->format('d/m'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
