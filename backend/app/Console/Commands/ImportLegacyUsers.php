<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Migra usuários do app antigo (Firebase) para o Sancta.
 *
 * Entrada: JSON exportado do Firestore/Firebase Auth no formato
 * [{"email": "...", "name": "...", "google_uid": "...", "total_doado": 30.0, "favoritos": ["1","3"]}]
 *
 * - Cria a conta (login pelo Google funciona automaticamente pelo e-mail).
 * - Benfeitores (quem doou no app antigo) recebem Sancta+ de cortesia como gratidão:
 *   R$ 5 → 1 mês · R$ 20 → 3 meses · R$ 50 → 6 meses · R$ 100+ → 12 meses.
 */
class ImportLegacyUsers extends Command
{
    protected $signature = 'sancta:import-legacy-users {file : Caminho do JSON exportado} {--dry-run}';

    protected $description = 'Importa usuários e benfeitores do app antigo (Firebase)';

    public function handle(EntitlementService $entitlements): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($path), true);
        if (! is_array($rows)) {
            $this->error('JSON inválido.');

            return self::FAILURE;
        }

        $admin = User::query()->where('role', UserRole::SuperAdmin->value)->firstOrFail();
        $created = $granted = $skipped = 0;

        foreach ($rows as $row) {
            $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("[simulação] {$email}");

                continue;
            }

            DB::transaction(function () use ($row, $email, $admin, $entitlements, &$created, &$granted): void {
                $user = User::query()->where('email', $email)->first();

                if (! $user) {
                    $user = User::query()->create([
                        'name' => Str::limit(strip_tags((string) ($row['name'] ?? Str::before($email, '@'))), 120, ''),
                        'email' => $email,
                    ]);
                    $user->forceFill(['email_verified_at' => now(), 'source' => 'import', 'terms_accepted_at' => null])->save();
                    $created++;
                }

                $favorites = array_filter((array) ($row['favoritos'] ?? []));
                if ($favorites) {
                    $ids = Church::query()->whereIn('id', $favorites)->pluck('id');
                    $user->favoriteChurches()->syncWithoutDetaching($ids);
                }

                $donated = (float) ($row['total_doado'] ?? 0);
                $days = match (true) {
                    $donated >= 100 => 365,
                    $donated >= 50 => 180,
                    $donated >= 20 => 90,
                    $donated >= 5 => 30,
                    default => 0,
                };

                if ($days > 0 && ! $user->subscriptions()->where('notes', 'like', 'Benfeitor do app antigo%')->exists()) {
                    $entitlements->grantManual($user, null, $days, $admin, 'Benfeitor do app antigo (R$ '.number_format($donated, 2, ',', '.').')');
                    $granted++;
                }
            });
        }

        $this->info("Criados: {$created} · Cortesias de gratidão: {$granted} · Ignorados: {$skipped}");

        return self::SUCCESS;
    }
}
