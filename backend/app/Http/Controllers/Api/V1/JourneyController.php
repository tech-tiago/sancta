<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JourneyResource;
use App\Models\Journey;
use App\Models\JourneyProgress;
use App\Services\Community\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class JourneyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['type' => ['sometimes', Rule::in(['journey', 'novena', 'reading_plan', 'challenge'])]]);

        return JourneyResource::collection(
            Journey::query()->published()->withCount('steps')
                ->when($request->input('type'), fn ($q, $type) => $q->where('type', $type))
                ->orderByDesc('is_featured')->orderBy('sort_order')
                ->get()
        );
    }

    public function show(string $slug): JourneyResource
    {
        $journey = Journey::query()->published()->with('steps.prayer')->withCount('steps')->where('slug', $slug)->firstOrFail();

        return (new JourneyResource($journey))->detailed();
    }

    public function completeStep(Request $request, string $slug, int $position, StreakService $streaks): JsonResponse
    {
        $journey = Journey::query()->published()->where('slug', $slug)->firstOrFail();
        $user = $request->user();

        if ($journey->is_premium && $position > 1 && ! $user->isPremium()) {
            return response()->json(['message' => 'Conteúdo exclusivo do Sancta+.', 'paywall' => true], 402);
        }

        $step = $journey->steps()->where('position', $position)->firstOrFail();

        $progress = JourneyProgress::query()->firstOrCreate(
            ['user_id' => $user->id, 'journey_step_id' => $step->id],
            ['journey_id' => $journey->id, 'completed_at' => now()]
        );

        if ($progress->wasRecentlyCreated) {
            $streaks->record($user, 'journey_step', $step->id, (int) ($step->duration_seconds ?: 300));
        }

        $completed = $user->journeyProgress()->where('journey_id', $journey->id)->count();

        return response()->json([
            'completed_steps' => $completed,
            'steps_count' => $journey->steps()->count(),
            'finished' => $completed >= $journey->steps()->count(),
        ]);
    }
}
