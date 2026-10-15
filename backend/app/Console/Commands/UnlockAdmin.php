<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\Integrations;
use Illuminate\Console\Command;

/**
 * Emergência: alguém se trancou fora do painel (lista de IPs errada ou 2FA perdido).
 * Remove a restrição de IP e o 2FA obrigatório definidos no PAINEL — o que estiver
 * no .env do servidor continua valendo. Exige acesso ao servidor (terminal).
 */
class UnlockAdmin extends Command
{
    protected $signature = 'sancta:admin-unlock';

    protected $description = 'Remove a restrição de IP e o 2FA obrigatório definidos no painel (emergência)';

    public function handle(Integrations $integrations): int
    {
        $integrations->forget('admin.allowed_ips');
        $integrations->forget('admin.require_mfa');

        AuditLog::record('integrations.unlocked', null, [], ['campos' => ['admin.allowed_ips', 'admin.require_mfa']]);

        $this->info('Restrição de IP e 2FA obrigatório do painel foram removidos.');
        $this->line('Valores definidos no .env (SANCTA_ADMIN_IPS / SANCTA_ADMIN_REQUIRE_MFA) continuam valendo.');

        return self::SUCCESS;
    }
}
