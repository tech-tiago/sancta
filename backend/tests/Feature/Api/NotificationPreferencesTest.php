<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Preferências de notificação da conta (PATCH /me): o app salva aqui os lembretes de oração
 * da manhã (daily_reminder/reminder_time) e da noite (evening_reminder/evening_reminder_time).
 */
class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_morning_and_evening_reminders(): void
    {
        $user = $this->makeUser();
        $prefs = [
            'daily_reminder' => true,
            'reminder_time' => '06:30',
            'evening_reminder' => true,
            'evening_reminder_time' => '21:15',
            'liturgy' => true,
            'news' => false,
            'churches' => true,
        ];

        $this->patchJson('/api/v1/me', ['notification_preferences' => $prefs], $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('notification_preferences.evening_reminder', true)
            ->assertJsonPath('notification_preferences.evening_reminder_time', '21:15')
            ->assertJsonPath('notification_preferences.reminder_time', '06:30');

        $this->assertSame($prefs, $user->fresh()->notification_preferences);
        $this->getJson('/api/v1/me', $this->bearer($user))->assertJsonPath('notification_preferences.evening_reminder_time', '21:15');
    }

    public function test_rejects_unknown_keys_and_invalid_times(): void
    {
        $user = $this->makeUser();

        $this->patchJson('/api/v1/me', ['notification_preferences' => ['evening_reminder' => true, 'qualquer_coisa' => true]], $this->bearer($user))
            ->assertUnprocessable();
        $this->patchJson('/api/v1/me', ['notification_preferences' => ['evening_reminder_time' => '25:00']], $this->bearer($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('notification_preferences.evening_reminder_time');
        $this->patchJson('/api/v1/me', ['notification_preferences' => ['evening_reminder' => 'talvez']], $this->bearer($user))
            ->assertUnprocessable();

        $this->assertNull($user->fresh()->notification_preferences);
    }
}
