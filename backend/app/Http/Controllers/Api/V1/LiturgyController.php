<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Content\LiturgyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiturgyController extends Controller
{
    public function show(Request $request, LiturgyService $liturgy): JsonResponse
    {
        $request->validate(['date' => ['sometimes', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01']]);

        $date = $request->filled('date') ? Carbon::createFromFormat('Y-m-d', $request->string('date'))->startOfDay() : now();
        $day = $liturgy->forDate($date);

        if (! $day) {
            return response()->json(['message' => 'Liturgia indisponível para esta data.'], 404);
        }

        $premium = $request->user('sanctum')?->isPremium() ?? false;

        return response()->json([
            'date' => $day->date->toDateString(),
            'title' => $day->title,
            'color' => $day->color,
            'color_hex' => $day->colorHex(),
            'readings' => $day->readings ?? [],
            // Mapa {collect, offerings, communion}: vazio sai como `{}` (o app lê como objeto, nunca lista).
            'prayers' => (object) ($day->prayers ?? []),
            'homily' => $day->homily ? ($premium ? $day->homily : null) : null,
            'homily_author' => $day->homily_author,
            'homily_locked' => (bool) $day->homily && ! $premium,
        ]);
    }
}
