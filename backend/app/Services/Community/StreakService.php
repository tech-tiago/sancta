<?php

namespace App\Services\Community;

use App\Models\PrayerSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Registra tempo de oração e mantém a sequência de dias ("streak"), como no Hallow.
 */
class StreakService
{
    public function record(User $user, string $contentType, ?int $contentId, int $seconds): User
    {
        $seconds = max(1, min($seconds, 4 * 3600)); // limite de sanidade: 4 h por sessão
        $today = now()->toDateString();

        return DB::transaction(function () use ($user, $contentType, $contentId, $seconds, $today): User {
            /** @var User $user */
            $user = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            PrayerSession::query()->create([
                'user_id' => $user->id,
                'content_type' => $contentType,
                'content_id' => $contentId,
                'seconds' => $seconds,
                'prayed_on' => $today,
            ]);

            $last = $user->last_prayed_on?->toDateString();

            if ($last !== $today) {
                $streak = $last === now()->subDay()->toDateString() ? $user->current_streak + 1 : 1;
                $user->forceFill([
                    'current_streak' => $streak,
                    'longest_streak' => max($user->longest_streak, $streak),
                    'last_prayed_on' => $today,
                ]);
            }

            $user->forceFill(['total_prayer_seconds' => $user->total_prayer_seconds + $seconds])->save();

            return $user;
        });
    }

    /** Sequência atual considerando que ontem ainda vale (o dia de hoje pode não ter terminado). */
    public function currentStreak(User $user): int
    {
        $last = $user->last_prayed_on;
        if (! $last) {
            return 0;
        }

        return $last->isToday() || $last->isYesterday() ? $user->current_streak : 0;
    }

    /** Dias com oração nos últimos 7 dias (para o calendário do perfil). */
    public function lastSevenDays(User $user): array
    {
        $days = $user->prayerSessions()
            ->where('prayed_on', '>=', now()->subDays(6)->toDateString())
            ->selectRaw('prayed_on, SUM(seconds) as total')
            ->groupBy('prayed_on')
            ->pluck('total', 'prayed_on')
            ->mapWithKeys(fn ($total, $date) => [substr((string) $date, 0, 10) => (int) $total]);

        return collect(range(6, 0))->map(function ($ago) use ($days) {
            $date = now()->subDays($ago)->toDateString();

            return ['date' => $date, 'seconds' => $days[$date] ?? 0];
        })->all();
    }
}
