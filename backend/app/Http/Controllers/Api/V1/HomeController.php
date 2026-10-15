<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JourneyResource;
use App\Http\Resources\NewsArticleResource;
use App\Http\Resources\PrayerResource;
use App\Models\DailyVerse;
use App\Models\Event;
use App\Models\Journey;
use App\Models\LiveChannel;
use App\Models\NewsArticle;
use App\Models\Prayer;
use App\Models\Saint;
use App\Services\Community\StreakService;
use App\Services\Content\LiturgyService;
use App\Services\Content\PeriodPrayers;
use App\Services\Live\LiveDirectory;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tela "Hoje" do app em UMA requisição (app leve: menos idas à rede).
 */
class HomeController extends Controller
{
    public function index(Request $request, LiturgyService $liturgy, Settings $settings, StreakService $streaks, LiveDirectory $live, PeriodPrayers $periodPrayers): JsonResponse
    {
        $liveEnabled = $settings->bool('features.live');
        $liveChannels = $liveEnabled ? $live->channels() : collect();

        $user = $request->user('sanctum');
        $premium = $user?->isPremium() ?? false;
        $today = now();

        $verse = DailyVerse::query()->whereDate('date', $today->toDateString())->first();
        $liturgyDay = $liturgy->forDate($today);
        $saint = Saint::query()->where('is_active', true)->where('feast_month', $today->month)->where('feast_day', $today->day)->first();

        $continue = null;
        if ($user) {
            $lastJourneyId = $user->journeyProgress()->latest('completed_at')->value('journey_id');
            $journey = $lastJourneyId ? Journey::query()->published()->withCount('steps')->find($lastJourneyId) : null;
            if ($journey) {
                $continue = new JourneyResource($journey);
            }
        }

        return response()->json([
            'greeting' => $this->greeting($user?->firstName()),
            'streak' => $user ? $streaks->currentStreak($user) : 0,
            'daily_verse' => $verse ? [
                'reference' => $verse->reference,
                'text' => $verse->text,
                'image_url' => $verse->imageUrl(),
                'reflection_title' => $verse->reflection_title,
                'reflection' => (! $verse->reflection_is_premium || $premium) ? $verse->reflection : null,
                'reflection_locked' => $verse->reflection_is_premium && ! $premium,
            ] : null,
            'liturgy' => $liturgyDay ? [
                'date' => $liturgyDay->date->toDateString(),
                'title' => $liturgyDay->title,
                'color' => $liturgyDay->color,
                'color_hex' => $liturgyDay->colorHex(),
                'gospel_reference' => collect($liturgyDay->readings)->firstWhere('label', 'Evangelho')['reference'] ?? null,
            ] : null,
            'saint' => $saint ? [
                'name' => $saint->name,
                'title' => $saint->title,
                'biography' => $saint->biography,
                'image_url' => $saint->imageUrl(),
            ] : null,
            'continue_journey' => $continue,
            'featured_journeys' => JourneyResource::collection(
                Journey::query()->published()->where('is_featured', true)->withCount('steps')->orderBy('sort_order')->limit(6)->get()
            ),
            // Oração do período (cartão herói da tela Hoje e widget): painel → Configurações → Aplicativo.
            'period_prayers' => array_map(fn (?Prayer $p) => $p ? new PrayerResource($p) : null, $periodPrayers->resolve()),
            'featured_prayers' => PrayerResource::collection(
                Prayer::query()->published()->where('is_featured', true)->with('category')->orderBy('sort_order')->limit(8)->get()
            ),
            'news' => $settings->bool('features.news') ? NewsArticleResource::collection(
                NewsArticle::query()->published()->with('source')->limit(5)->get()
            ) : [],
            'live_channels' => $liveChannels->take(6)->map(fn (LiveChannel $c) => [
                'id' => $c->id, 'name' => $c->name, 'thumbnail_url' => $c->thumbnailUrl(), 'url' => $c->watchUrl(), 'type' => $c->type,
                'is_live' => $c->isLive(),
            ])->values(),
            // Destaque "Ao vivo agora": canais ao vivo (detectado) ou no horário da agenda.
            'live_now' => $liveEnabled ? $live->liveNow(6, $liveChannels) : [],
            'events' => $settings->bool('features.events') ? Event::query()->upcoming()->limit(3)->get()->map(fn ($e) => [
                'slug' => $e->slug, 'title' => $e->title, 'starts_at' => $e->starts_at->toIso8601String(), 'location' => $e->location,
                'cover_url' => $e->coverUrl(), 'is_premium' => $e->is_premium,
            ]) : [],
            'is_premium' => $premium,
        ]);
    }

    private function greeting(?string $name): array
    {
        $hour = (int) now()->format('H');
        $salutation = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');

        return [
            'title' => $name ? "{$salutation}, {$name}!" : "{$salutation}!",
            'subtitle' => 'Que hoje seja um dia de bênçãos.',
        ];
    }
}
