<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ThemePreference;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\UserResource;
use App\Models\Device;
use App\Services\Account\AccountService;
use App\Services\Community\StreakService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class MeController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(Request $request): UserResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'theme' => ['sometimes', Rule::enum(ThemePreference::class)],
            'marketing_opt_in' => ['sometimes', 'boolean'],
            // Lembretes de oração do app: manhã (daily_reminder/reminder_time) e noite (evening_*).
            'notification_preferences' => ['sometimes', 'array:daily_reminder,reminder_time,evening_reminder,evening_reminder_time,liturgy,news,churches'],
            'notification_preferences.daily_reminder' => ['sometimes', 'boolean'],
            'notification_preferences.reminder_time' => ['sometimes', 'date_format:H:i'],
            'notification_preferences.evening_reminder' => ['sometimes', 'boolean'],
            'notification_preferences.evening_reminder_time' => ['sometimes', 'date_format:H:i'],
            'notification_preferences.liturgy' => ['sometimes', 'boolean'],
            'notification_preferences.news' => ['sometimes', 'boolean'],
            'notification_preferences.churches' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name'])) {
            $data['name'] = strip_tags(trim($data['name']));
        }

        $user = $request->user();
        $user->fill($data)->save();

        return new UserResource($user);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => [$user->password ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        if ($user->password && ! Hash::check((string) $data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Senha atual incorreta.']);
        }

        $this->accounts->changePassword($user, $data['password'], $user->currentAccessToken()?->id);

        return response()->json(['message' => 'Senha alterada. As outras sessões foram encerradas.']);
    }

    public function uploadAvatar(Request $request): UserResource
    {
        $request->validate([
            // mimes valida o CONTEÚDO real do arquivo; dimensões evitam "bombas" de imagem
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('sancta.security.max_user_upload_kb'), 'dimensions:max_width=4000,max_height=4000'],
        ]);

        $user = $request->user();
        $old = $user->avatar_path;

        $path = $request->file('avatar')->storeAs('avatars', Str::uuid().'.'.$request->file('avatar')->guessExtension(), 'public');
        $user->forceFill(['avatar_path' => $path])->save();

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return new UserResource($user);
    }

    public function stats(Request $request, StreakService $streaks): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'current_streak' => $streaks->currentStreak($user),
            'longest_streak' => $user->longest_streak,
            'total_prayer_minutes' => intdiv((int) $user->total_prayer_seconds, 60),
            'sessions_count' => $user->prayerSessions()->count(),
            'journeys_completed_steps' => $user->journeyProgress()->count(),
            'last_seven_days' => $streaks->lastSevenDays($user),
        ]);
    }

    public function subscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $active = $user->activeSubscription();

        return response()->json([
            'is_premium' => $active !== null,
            'active' => $active ? new SubscriptionResource($active) : null,
            'payments' => $user->payments()->with('plan')->latest()->limit(20)->get()->map(fn ($p) => [
                'id' => $p->uuid,
                'plan' => $p->plan?->name,
                'method' => $p->method->getLabel(),
                'status' => $p->status->value,
                'status_label' => $p->status->getLabel(),
                'amount' => Money::format($p->amount_cents),
                'created_at' => $p->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:255'],
            'platform' => ['sometimes', Rule::in(['android', 'ios'])],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:20'],
            'device_model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notifications_enabled' => ['sometimes', 'boolean'],
        ]);

        // Um token pertence a um único aparelho: se trocou de conta, transfere.
        $device = Device::query()->firstOrNew(['fcm_token' => $data['fcm_token']]);
        $device->fill($data + ['last_seen_at' => now()]);
        $device->user()->associate($request->user());
        $device->save();

        return response()->json(['message' => 'Dispositivo registrado.']);
    }

    public function removeDevice(Request $request): JsonResponse
    {
        $data = $request->validate(['fcm_token' => ['required', 'string', 'max:255']]);

        $request->user()->devices()->where('fcm_token', $data['fcm_token'])->delete();

        return response()->json(['message' => 'Dispositivo removido.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'confirmation' => ['required', Rule::in(['EXCLUIR'])],
            'password' => [$user->password ? 'required' : 'nullable', 'string'],
        ]);

        if ($user->password && ! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'Senha incorreta.']);
        }

        $this->accounts->delete($user);

        return response()->json(['message' => 'Sua conta e seus dados foram excluídos.']);
    }
}
