<?php

namespace App\Console\Commands;

use App\Services\Content\LiturgyService;
use Illuminate\Console\Command;

class PrefetchLiturgy extends Command
{
    protected $signature = 'sancta:prefetch-liturgy {--days=7}';

    protected $description = 'Importa a liturgia diária dos próximos dias (respeita dias travados no painel)';

    public function handle(LiturgyService $liturgy): int
    {
        $days = max(1, min(31, (int) $this->option('days')));

        for ($i = 0; $i < $days; $i++) {
            $date = today()->addDays($i);
            $result = $liturgy->fetch($date);
            $this->line($date->format('d/m/Y').' '.($result ? '✓ '.$result->title : '✗ indisponível'));
        }

        return self::SUCCESS;
    }
}
