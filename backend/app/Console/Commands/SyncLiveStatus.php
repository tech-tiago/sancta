<?php

namespace App\Console\Commands;

use App\Services\Live\LiveStatusService;
use App\Services\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Detecta transmissões ao vivo/agendadas dos canais do YouTube (agendado a cada 3 min).
 * Nunca falha por problema de rede: registra um aviso no log e termina com sucesso.
 */
class SyncLiveStatus extends Command
{
    protected $signature = 'sancta:live-sync {--force : Roda mesmo com a área "Ao vivo" desligada nas configurações}';

    protected $description = 'Atualiza o status ao vivo dos canais do YouTube (RSS + YouTube Data API) e avisa por push quando uma live começa';

    public function handle(LiveStatusService $service, Settings $settings): int
    {
        if (! $settings->bool('features.live') && ! $this->option('force')) {
            $this->line('Área "Ao vivo" desligada nas configurações — nada a fazer.');

            return self::SUCCESS;
        }

        try {
            $stats = $service->syncAll();
        } catch (Throwable $e) {
            Log::warning('sancta:live-sync falhou', ['erro' => Str::limit($e->getMessage(), 300)]);
            $this->warn('Falha ao sincronizar: '.Str::limit($e->getMessage(), 200));

            return self::SUCCESS;
        }

        if ($stats['without_key']) {
            $this->line('Sem chave da YouTube Data API: status pela agenda (cadastre em Integrações e segurança → Conteúdo).');
        }

        $this->info(sprintf(
            'Canais: %d · Ao vivo: %d · Agendados: %d · Avisos enviados: %d · Falhas: %d',
            $stats['channels'], $stats['live'], $stats['upcoming'], $stats['notified'], $stats['errors'],
        ));

        return self::SUCCESS;
    }
}
