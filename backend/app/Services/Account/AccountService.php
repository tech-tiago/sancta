<?php

namespace App\Services\Account;

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Operações sensíveis da conta: login registrado, troca de senha e exclusão (LGPD / Google Play).
 */
class AccountService
{
    public function recordLogin(User $user, ?string $ip): void
    {
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->saveQuietly();
    }

    /** Troca a senha e derruba todas as outras sessões/tokens. */
    public function changePassword(User $user, string $newPassword, ?int $keepTokenId = null): void
    {
        $user->forceFill([
            'password' => $newPassword,
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        $user->tokens()->when($keepTokenId, fn ($q) => $q->whereKeyNot($keepTokenId))->delete();
        AuditLog::record('user.password_changed', $user, [], [], $user);
    }

    /**
     * Exclusão de conta: remove dados pessoais e de uso; mantém registros de pagamento
     * anonimizados (obrigação legal/fiscal). Irreversível.
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->tokens()->delete();
            $user->devices()->delete();
            $user->bibleAnnotations()->delete();
            $user->readingProgress()->delete();
            $user->favoriteChurches()->detach();
            $user->favoritePrayers()->detach();
            $user->prayerSessions()->delete();
            $user->journeyProgress()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            $user->forceFill([
                'name' => 'Conta excluída',
                'email' => "excluido-{$user->uuid}@sancta.invalid",
                'password' => null,
                'google_id' => null,
                'avatar_path' => null,
                'avatar_url' => null,
                'notification_preferences' => null,
                'marketing_opt_in' => false,
                'last_login_ip' => null,
                'app_authentication_secret' => null,
                'app_authentication_recovery_codes' => null,
                'status' => UserStatus::Banned,
                'suspension_reason' => 'Conta excluída pelo titular',
                'remember_token' => null,
            ])->saveQuietly();

            $user->delete(); // soft delete: preserva vínculos de pagamentos anonimizados
        });
    }
}
