<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\Community\StreakService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subscription = $this->activeSubscription();

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'first_name' => $this->firstName(),
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'avatar_url' => $this->avatarUrl(),
            'has_password' => $this->password !== null,
            'google_linked' => $this->google_id !== null,
            'theme' => $this->theme?->value ?? 'system',
            // Mapa: um `{}` salvo volta como `{}` (não `[]`), que é o que o app espera.
            'notification_preferences' => (object) ($this->notification_preferences ?? [
                'daily_reminder' => true,
                'reminder_time' => '07:00',
                'liturgy' => true,
                'news' => false,
                'churches' => true,
            ]),
            'marketing_opt_in' => $this->marketing_opt_in,
            'is_premium' => $subscription !== null,
            'subscription' => $subscription ? new SubscriptionResource($subscription) : null,
            'stats' => [
                'current_streak' => app(StreakService::class)->currentStreak($this->resource),
                'longest_streak' => $this->longest_streak,
                'total_prayer_minutes' => intdiv((int) $this->total_prayer_seconds, 60),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
