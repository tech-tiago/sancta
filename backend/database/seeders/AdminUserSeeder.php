<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Cria o primeiro super administrador a partir do .env (SANCTA_ADMIN_*).
 * Se SANCTA_ADMIN_PASSWORD estiver vazio, gera uma senha forte e a exibe UMA vez.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('sancta.admin.seed');

        if (User::query()->where('email', $config['email'])->exists()) {
            $this->command?->info("Administrador {$config['email']} já existe.");

            return;
        }

        $password = $config['password'] ?: Str::password(20);

        $admin = User::query()->create([
            'name' => $config['name'],
            'email' => $config['email'],
            'password' => $password,
        ]);

        $admin->forceFill([
            'role' => UserRole::SuperAdmin,
            'email_verified_at' => now(),
            'password_changed_at' => now(),
            'terms_accepted_at' => now(),
            'source' => 'admin',
        ])->save();

        $this->command?->warn("Super administrador criado: {$config['email']}");
        if (! $config['password']) {
            $this->command?->warn("Senha gerada (anote agora, não será exibida novamente): {$password}");
        }
    }
}
