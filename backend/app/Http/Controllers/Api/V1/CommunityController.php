<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementInteraction;
use App\Models\PrayerIntention;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Intenções de oração da comunidade e avisos/pop-ups do app. */
class CommunityController extends Controller
{
    public function intentions(Request $request, Settings $settings): JsonResponse
    {
        abort_unless($settings->bool('features.intentions'), 404);

        $user = $request->user('sanctum');
        $items = PrayerIntention::query()
            ->where('status', ModerationStatus::Approved->value)
            ->with('user:id,name')
            ->latest('moderated_at')
            ->paginate(20);

        $prayed = $user
            ? DB::table('intention_prayers')->where('user_id', $user->id)->whereIn('prayer_intention_id', $items->pluck('id'))->pluck('prayer_intention_id')->all()
            : [];

        return response()->json([
            'data' => $items->getCollection()->map(fn (PrayerIntention $i) => [
                'id' => $i->uuid,
                'text' => $i->text,
                'author' => $i->displayName(),
                'prayers_count' => $i->prayers_count,
                'prayed' => in_array($i->id, $prayed, true),
                'created_at' => $i->created_at->toIso8601String(),
            ]),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage()],
        ]);
    }

    public function submitIntention(Request $request, Settings $settings): JsonResponse
    {
        abort_unless($settings->bool('features.intentions'), 404);

        $data = $request->validate([
            'text' => ['required', 'string', 'min:10', 'max:500'],
            'is_anonymous' => ['sometimes', 'boolean'],
        ]);

        $intention = new PrayerIntention([
            'text' => strip_tags(trim($data['text'])),
            'is_anonymous' => $data['is_anonymous'] ?? true,
        ]);
        $intention->user_id = $request->user()->id;
        $intention->status = ModerationStatus::Pending;
        $intention->save();

        return response()->json(['message' => 'Sua intenção foi enviada e aparecerá após revisão. Estamos rezando com você. 🙏'], 201);
    }

    public function pray(Request $request, string $uuid): JsonResponse
    {
        $intention = PrayerIntention::query()->where('uuid', $uuid)->where('status', ModerationStatus::Approved->value)->firstOrFail();

        $inserted = DB::table('intention_prayers')->insertOrIgnore([
            'user_id' => $request->user()->id,
            'prayer_intention_id' => $intention->id,
            'created_at' => now(),
        ]);

        if ($inserted) {
            $intention->increment('prayers_count');
        }

        return response()->json(['prayers_count' => $intention->fresh()->prayers_count, 'prayed' => true]);
    }

    public function announcements(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        $items = Announcement::query()->live()->whereIn('platform', ['app', 'both'])->forViewer($user)->limit(10)->get();

        return response()->json($items->map(fn (Announcement $a) => [
            'id' => $a->id,
            'title' => $a->title,
            'body' => $a->body,
            'image_url' => $a->imageUrl(),
            'kind' => $a->kind,
            'category' => $a->category,
            'cta_label' => $a->cta_label,
            'cta_url' => $a->cta_url,
            'frequency' => $a->frequency,
            'dismissible' => $a->dismissible,
            'priority' => $a->priority,
            'updated_at' => $a->updated_at->toIso8601String(),
        ]));
    }

    public function announcementInteraction(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['view', 'click', 'dismiss'])]]);
        $announcement = Announcement::query()->live()->findOrFail($id);

        AnnouncementInteraction::query()->create([
            'announcement_id' => $announcement->id,
            'user_id' => $request->user('sanctum')?->id,
            'action' => $data['action'],
        ]);

        // Contadores sem tocar em updated_at: o app usa updated_at para saber se o aviso
        // foi editado no painel (e então exibi-lo de novo, mesmo com frequência "uma vez").
        $counter = match ($data['action']) {
            'view' => 'views_count',
            'click' => 'clicks_count',
            default => null,
        };
        if ($counter !== null) {
            Announcement::query()->whereKey($announcement->id)->toBase()->increment($counter);
        }

        return response()->json(['ok' => true]);
    }
}
