<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsArticleResource;
use App\Http\Resources\PlanResource;
use App\Models\Event;
use App\Models\Faq;
use App\Models\NewsArticle;
use App\Models\Page;
use App\Models\Plan;
use App\Models\Saint;
use App\Services\Live\LiveDirectory;
use App\Support\Html;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Conteúdos simples somente leitura: notícias, lives, eventos, santos, planos, páginas e FAQ. */
class ContentController extends Controller
{
    public function news(Request $request): AnonymousResourceCollection
    {
        return NewsArticleResource::collection(NewsArticle::query()->published()->with('source')->paginate(20));
    }

    public function newsShow(int $id): NewsArticleResource
    {
        return (new NewsArticleResource(NewsArticle::query()->published()->with('source')->findOrFail($id)))->detailed();
    }

    /** Canais ao vivo com status, agenda e próximo horário (contrato em docs/API.md). */
    public function live(LiveDirectory $directory): JsonResponse
    {
        return response()->json($directory->all());
    }

    public function events(Request $request): JsonResponse
    {
        $premium = $request->user('sanctum')?->isPremium() ?? false;

        return response()->json(Event::query()->upcoming()->with('church')->limit(50)->get()->map(fn (Event $e) => [
            'slug' => $e->slug,
            'title' => $e->title,
            'description' => (! $e->is_premium || $premium) ? Html::toText($e->description) : null,
            'cover_url' => $e->coverUrl(),
            'location' => $e->location,
            'church' => $e->church?->name,
            'starts_at' => $e->starts_at->toIso8601String(),
            'ends_at' => $e->ends_at?->toIso8601String(),
            'is_online' => $e->is_online,
            'link' => (! $e->is_premium || $premium) ? $e->link : null,
            'is_premium' => $e->is_premium,
            'locked' => $e->is_premium && ! $premium,
        ]));
    }

    public function saintOfDay(): JsonResponse
    {
        $saint = Saint::query()->where('is_active', true)->where('feast_month', now()->month)->where('feast_day', now()->day)->first();

        return response()->json($saint ? [
            'name' => $saint->name, 'title' => $saint->title, 'biography' => $saint->biography,
            'prayer' => $saint->prayer, 'image_url' => $saint->imageUrl(),
        ] : null);
    }

    public function plans(): AnonymousResourceCollection
    {
        // Economia calculada contra o plano mensal ativo do MESMO canal (app).
        return PlanResource::collection(Plan::withPricing(Plan::query()->active()->where('available_on_app', true)->get()));
    }

    public function page(string $slug): JsonResponse
    {
        $page = Page::query()->where('slug', $slug)->where('is_published', true)->firstOrFail();

        return response()->json([
            'slug' => $page->slug,
            'title' => $page->title,
            'blocks' => Html::toBlocks($page->content),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ]);
    }

    public function faqs(): JsonResponse
    {
        return response()->json(Faq::query()->for('app')->get(['question', 'answer']));
    }
}
