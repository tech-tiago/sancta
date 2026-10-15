<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PrayerResource;
use App\Models\Category;
use App\Models\Prayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PrayerController extends Controller
{
    public function categories(): JsonResponse
    {
        return response()->json(Category::query()->active()->withCount(['prayers' => fn ($q) => $q->published()])->get()->map(fn ($c) => [
            'slug' => $c->slug,
            'name' => $c->name,
            'description' => $c->description,
            'icon' => $c->icon,
            'color' => $c->color,
            'prayers_count' => $c->prayers_count,
        ]));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'category' => ['sometimes', 'string', 'max:100'],
            'q' => ['sometimes', 'string', 'max:100'],
            'premium' => ['sometimes', 'boolean'],
        ]);

        $prayers = Prayer::query()->published()->with('category')
            ->when($request->input('category'), fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($request->input('q'), fn ($q, $term) => $q->where('title', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when($request->has('premium'), fn ($q) => $q->where('is_premium', $request->boolean('premium')))
            ->orderBy('sort_order')->orderBy('title')
            ->paginate(40);

        return PrayerResource::collection($prayers);
    }

    public function show(string $slug): PrayerResource
    {
        $prayer = Prayer::query()->published()->with('category')->where('slug', $slug)->firstOrFail();
        $prayer->increment('views_count');

        return (new PrayerResource($prayer))->detailed();
    }

    public function favorites(Request $request): AnonymousResourceCollection
    {
        return PrayerResource::collection(
            $request->user()->favoritePrayers()->published()->with('category')->orderByPivot('created_at', 'desc')->get()
        );
    }

    public function toggleFavorite(Request $request, string $slug): JsonResponse
    {
        $prayer = Prayer::query()->published()->where('slug', $slug)->firstOrFail();
        $result = $request->user()->favoritePrayers()->toggle([$prayer->id]);

        return response()->json(['is_favorite' => ! empty($result['attached'])]);
    }
}
