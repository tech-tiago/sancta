<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Community\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrayerSessionController extends Controller
{
    public function store(Request $request, StreakService $streaks): JsonResponse
    {
        $data = $request->validate([
            'content_type' => ['required', Rule::in(['prayer', 'journey_step', 'bible', 'liturgy', 'rosary', 'free'])],
            'content_id' => ['nullable', 'integer', 'min:1'],
            'seconds' => ['required', 'integer', 'min:10', 'max:14400'],
        ]);

        $user = $streaks->record($request->user(), $data['content_type'], $data['content_id'] ?? null, $data['seconds']);

        return response()->json([
            'current_streak' => $streaks->currentStreak($user),
            'longest_streak' => $user->longest_streak,
            'total_prayer_minutes' => intdiv((int) $user->total_prayer_seconds, 60),
        ], 201);
    }
}
