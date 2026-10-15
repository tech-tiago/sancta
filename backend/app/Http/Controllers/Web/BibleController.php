<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BibleBook;
use App\Models\BibleVerse;
use App\Models\BibleVersion;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Bíblia online no site (leitura e busca), sem necessidade de login. */
class BibleController extends Controller
{
    public function index(): View
    {
        $this->ensureEnabled();
        $version = BibleVersion::default();
        abort_unless($version, 404);

        $books = $version->books()->get()->groupBy('testament');

        return view('site.bible.index', compact('version', 'books'));
    }

    public function chapter(string $book, int $chapter = 1): View
    {
        $this->ensureEnabled();
        $version = BibleVersion::default();
        abort_unless($version, 404);

        $bookModel = BibleBook::query()->where('bible_version_id', $version->id)->where('slug', $book)->firstOrFail();
        abort_if($chapter < 1 || $chapter > $bookModel->chapters_count, 404);

        $verses = BibleVerse::query()->where('bible_book_id', $bookModel->id)->where('chapter', $chapter)->orderBy('verse')->get(['verse', 'text']);

        $prev = $chapter > 1 ? [$bookModel->slug, $chapter - 1] : null;
        $next = $chapter < $bookModel->chapters_count ? [$bookModel->slug, $chapter + 1] : null;

        if (! $prev && ($p = BibleBook::query()->where('bible_version_id', $version->id)->where('position', $bookModel->position - 1)->first())) {
            $prev = [$p->slug, $p->chapters_count];
        }
        if (! $next && ($n = BibleBook::query()->where('bible_version_id', $version->id)->where('position', $bookModel->position + 1)->first())) {
            $next = [$n->slug, 1];
        }

        return view('site.bible.chapter', [
            'version' => $version,
            'book' => $bookModel,
            'chapter' => $chapter,
            'verses' => $verses,
            'prev' => $prev,
            'next' => $next,
            'books' => $version->books()->get(['name', 'slug', 'testament', 'chapters_count']),
        ]);
    }

    public function search(Request $request): View
    {
        $this->ensureEnabled();
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim((string) ($data['q'] ?? ''));
        $results = collect();

        if (mb_strlen($term) >= 3) {
            $version = BibleVersion::default();
            $query = BibleVerse::query()
                ->join('bible_books', 'bible_books.id', '=', 'bible_verses.bible_book_id')
                ->where('bible_books.bible_version_id', $version?->id)
                ->select('bible_verses.chapter', 'bible_verses.verse', 'bible_verses.text', 'bible_books.name', 'bible_books.slug', 'bible_books.abbreviation')
                ->orderBy('bible_books.position')->orderBy('bible_verses.chapter')->orderBy('bible_verses.verse')
                ->limit(60);

            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                $words = collect(preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $term)))
                    ->filter(fn ($w) => mb_strlen($w) >= 3)->map(fn ($w) => '+'.$w.'*')->implode(' ');
                $words !== '' ? $query->whereRaw('MATCH(bible_verses.text) AGAINST (? IN BOOLEAN MODE)', [$words]) : $query->whereRaw('1 = 0');
            } else {
                $query->where('bible_verses.text', 'like', '%'.addcslashes($term, '%_\\').'%');
            }

            $results = $query->get();
        }

        return view('site.bible.search', ['term' => $term, 'results' => $results]);
    }

    private function ensureEnabled(): void
    {
        abort_unless(app(Settings::class)->bool('features.web_bible'), 404);
    }
}
