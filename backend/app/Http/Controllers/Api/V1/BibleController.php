<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BibleAnnotation;
use App\Models\BibleBook;
use App\Models\BibleVerse;
use App\Models\BibleVersion;
use App\Models\ReadingProgress;
use App\Services\Content\AiExplanationService;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use RuntimeException;

class BibleController extends Controller
{
    public function books(): JsonResponse
    {
        $version = BibleVersion::default();
        abort_unless($version, 404, 'Bíblia indisponível.');

        $books = cache()->remember("bible.books.{$version->id}", 86400, fn () => $version->books()
            ->get(['id', 'testament', 'position', 'name', 'abbreviation', 'slug', 'chapters_count'])
            ->map(fn ($b) => $b->only(['testament', 'position', 'name', 'abbreviation', 'slug', 'chapters_count']))
            ->all());

        return response()->json([
            'version' => ['name' => $version->name, 'abbreviation' => $version->abbreviation, 'license_notice' => $version->license_notice],
            'books' => $books,
        ])->setPublic()->setMaxAge(3600);
    }

    public function chapter(Request $request, string $book, int $chapter): JsonResponse
    {
        $bookModel = $this->book($book);
        abort_if($chapter < 1 || $chapter > $bookModel->chapters_count, 404, 'Capítulo não encontrado.');

        $verses = cache()->remember("bible.chapter.{$bookModel->id}.{$chapter}", 86400, fn () => BibleVerse::query()
            ->where('bible_book_id', $bookModel->id)
            ->where('chapter', $chapter)
            ->orderBy('verse')
            ->get(['id', 'verse', 'text'])
            ->map(fn ($v) => ['id' => $v->id, 'number' => $v->verse, 'text' => $v->text])
            ->all());

        $user = $request->user('sanctum');
        $annotations = $user ? BibleAnnotation::query()
            ->where('user_id', $user->id)
            ->whereIn('bible_verse_id', array_column($verses, 'id'))
            ->get(['bible_verse_id', 'highlight_color', 'note', 'is_bookmarked'])
            ->keyBy('bible_verse_id') : collect();

        return response()->json([
            'book' => $bookModel->only(['name', 'abbreviation', 'slug', 'testament', 'chapters_count']),
            'chapter' => $chapter,
            'previous' => $this->neighbor($bookModel, $chapter, -1),
            'next' => $this->neighbor($bookModel, $chapter, 1),
            'verses' => array_map(fn ($v) => $v + [
                'highlight' => $annotations[$v['id']]->highlight_color ?? null,
                'note' => $annotations[$v['id']]->note ?? null,
                'bookmarked' => (bool) ($annotations[$v['id']]->is_bookmarked ?? false),
            ], $verses),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:100'],
            'testament' => ['sometimes', Rule::in(['AT', 'NT'])],
        ]);

        $version = BibleVersion::default();
        $term = trim($data['q']);

        $query = BibleVerse::query()
            ->join('bible_books', 'bible_books.id', '=', 'bible_verses.bible_book_id')
            ->where('bible_books.bible_version_id', $version?->id)
            ->when($data['testament'] ?? null, fn ($q, $t) => $q->where('bible_books.testament', $t))
            ->select('bible_verses.id', 'bible_verses.chapter', 'bible_verses.verse', 'bible_verses.text', 'bible_books.name', 'bible_books.slug', 'bible_books.abbreviation')
            ->orderBy('bible_books.position')->orderBy('bible_verses.chapter')->orderBy('bible_verses.verse')
            ->limit(50);

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            // Busca full-text em modo booleano; caracteres especiais removidos (sem injeção de operadores).
            $words = collect(preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $term)))
                ->filter(fn ($w) => mb_strlen($w) >= 3)->map(fn ($w) => '+'.$w.'*')->implode(' ');
            $words !== '' ? $query->whereRaw('MATCH(bible_verses.text) AGAINST (? IN BOOLEAN MODE)', [$words]) : $query->whereRaw('1 = 0');
        } else {
            $query->where('bible_verses.text', 'like', '%'.addcslashes($term, '%_\\').'%');
        }

        return response()->json([
            'query' => $term,
            'results' => $query->get()->map(fn ($v) => [
                'id' => $v->id,
                'reference' => "{$v->abbreviation} {$v->chapter},{$v->verse}",
                'book' => $v->name,
                'book_slug' => $v->slug,
                'chapter' => $v->chapter,
                'verse' => $v->verse,
                'text' => $v->text,
            ]),
        ]);
    }

    public function progress(Request $request): JsonResponse
    {
        $progress = $request->user()->readingProgress()->with('book')->first();

        return response()->json($progress ? [
            'book_slug' => $progress->book->slug,
            'book' => $progress->book->name,
            'chapter' => $progress->chapter,
            'verse' => $progress->verse,
            'updated_at' => $progress->updated_at->toIso8601String(),
        ] : null);
    }

    public function saveProgress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book' => ['required', 'string', 'max:60'],
            'chapter' => ['required', 'integer', 'min:1', 'max:200'],
            'verse' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $book = $this->book($data['book']);
        abort_if($data['chapter'] > $book->chapters_count, 422, 'Capítulo inválido.');

        ReadingProgress::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['bible_book_id' => $book->id, 'chapter' => $data['chapter'], 'verse' => $data['verse'] ?? null]
        );

        return response()->json(['message' => 'Progresso salvo.']);
    }

    public function annotations(Request $request): JsonResponse
    {
        $request->validate(['type' => ['sometimes', Rule::in(['bookmarks', 'notes', 'highlights'])]]);

        $items = $request->user()->bibleAnnotations()
            ->with('verse.book')
            ->when($request->input('type') === 'bookmarks', fn ($q) => $q->where('is_bookmarked', true))
            ->when($request->input('type') === 'notes', fn ($q) => $q->whereNotNull('note'))
            ->when($request->input('type') === 'highlights', fn ($q) => $q->whereNotNull('highlight_color'))
            ->latest('updated_at')
            ->paginate(30);

        return response()->json([
            'data' => $items->getCollection()->map(fn (BibleAnnotation $a) => [
                'verse_id' => $a->bible_verse_id,
                'reference' => $a->verse->reference(),
                'book_slug' => $a->verse->book->slug,
                'chapter' => $a->verse->chapter,
                'verse' => $a->verse->verse,
                'text' => $a->verse->text,
                'highlight' => $a->highlight_color,
                'note' => $a->note,
                'bookmarked' => $a->is_bookmarked,
                'updated_at' => $a->updated_at->toIso8601String(),
            ]),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()],
        ]);
    }

    public function annotate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'verse_ids' => ['required', 'array', 'min:1', 'max:50'],
            'verse_ids.*' => ['integer', 'distinct', 'exists:bible_verses,id'],
            'highlight' => ['sometimes', 'nullable', Rule::in(BibleAnnotation::COLORS)],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'bookmarked' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $changes = array_filter([
            'highlight_color' => array_key_exists('highlight', $data) ? ['v' => $data['highlight']] : null,
            'note' => array_key_exists('note', $data) ? ['v' => $data['note'] !== null ? strip_tags($data['note']) : null] : null,
            'is_bookmarked' => array_key_exists('bookmarked', $data) ? ['v' => $data['bookmarked']] : null,
        ]);

        foreach ($data['verse_ids'] as $verseId) {
            $annotation = BibleAnnotation::query()->where('user_id', $user->id)->where('bible_verse_id', $verseId)->first()
                ?? new BibleAnnotation(['bible_verse_id' => $verseId]);
            $annotation->user_id = $user->id;
            foreach ($changes as $field => $wrapped) {
                $annotation->{$field} = $wrapped['v'];
            }
            $annotation->isEmpty() ? ($annotation->exists && $annotation->delete()) : $annotation->save();
        }

        return response()->json(['message' => 'Anotações atualizadas.']);
    }

    public function explain(Request $request, AiExplanationService $ai, Settings $settings): JsonResponse
    {
        abort_unless($settings->bool('features.ai_explanations'), 404);

        $user = $request->user();
        if (! $user->isPremium()) {
            return response()->json(['message' => 'Explicações com IA são exclusivas do Sancta+.', 'paywall' => true], 402);
        }

        $data = $request->validate([
            'verse_ids' => ['required', 'array', 'min:1', 'max:10'],
            'verse_ids.*' => ['integer', 'distinct'],
        ]);

        $verses = BibleVerse::query()->with('book')->whereIn('id', $data['verse_ids'])->orderBy('id')->get();
        abort_if($verses->isEmpty(), 404, 'Versículos não encontrados.');

        $first = $verses->first();
        $last = $verses->last();
        $reference = $first->book->abbreviation." {$first->chapter},{$first->verse}".($verses->count() > 1 ? "-{$last->verse}" : '');

        $key = 'ai:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, config('sancta.ai.daily_limit', 30))) {
            return response()->json(['message' => 'Você atingiu o limite diário de explicações. Volte amanhã!'], 429);
        }
        RateLimiter::hit($key, 86400);

        try {
            $explanation = $ai->explain($reference, $verses->pluck('text')->implode(' '));
        } catch (RuntimeException $e) {
            RateLimiter::decrement($key);

            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json([
            'reference' => $reference,
            'explanation' => $explanation->content,
            'disclaimer' => 'Gerado por IA a partir da tradição católica. Para aprofundar, consulte o Catecismo e um sacerdote.',
        ]);
    }

    private function book(string $slug): BibleBook
    {
        $version = BibleVersion::default();
        abort_unless($version, 404, 'Bíblia indisponível.');

        return BibleBook::query()->where('bible_version_id', $version->id)->where('slug', $slug)->firstOrFail();
    }

    private function neighbor(BibleBook $book, int $chapter, int $direction): ?array
    {
        $target = $chapter + $direction;
        if ($target >= 1 && $target <= $book->chapters_count) {
            return ['book' => $book->slug, 'chapter' => $target];
        }

        $other = BibleBook::query()
            ->where('bible_version_id', $book->bible_version_id)
            ->where('position', $book->position + $direction)
            ->first();

        return $other ? ['book' => $other->slug, 'chapter' => $direction > 0 ? 1 : $other->chapters_count] : null;
    }
}
