<?php

namespace App\Services\Content;

use App\Models\Prayer;
use App\Services\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Oração do período" da tela Hoje e do widget "Oração do dia" (manhã, tarde e noite).
 *
 * Painel → Configurações → Aplicativo: três seletores de oração publicada. Sem escolha (ou se a
 * oração escolhida deixar de estar publicada), o servidor escolhe pela heurística de palavras-chave
 * no título/slug/categoria — a mesma ideia que o app usava sozinho. O app ainda tem a própria
 * heurística como reserva para servidores antigos (ver docs/API.md → `period_prayers`).
 */
class PeriodPrayers
{
    /** Chaves da API → chave da configuração. */
    public const SETTINGS = [
        'morning' => 'home.prayer_morning',
        'afternoon' => 'home.prayer_afternoon',
        'night' => 'home.prayer_night',
    ];

    /** Palavras-chave (sem acento, minúsculas), em ordem de preferência. */
    public const KEYWORDS = [
        'morning' => ['oracao da manha', 'manha', 'laudes', 'matinal', 'despertar'],
        'afternoon' => ['angelus', 'oracao da tarde', 'tarde', 'meio dia', 'misericordia', 'terco'],
        'night' => ['oracao da noite', 'noite', 'completas', 'exame', 'dormir', 'vesperas'],
    ];

    public function __construct(private readonly Settings $settings) {}

    /**
     * Orações (com a categoria carregada) de cada período; `null` quando não há nenhuma adequada.
     *
     * @return array{morning: ?Prayer, afternoon: ?Prayer, night: ?Prayer}
     */
    public function resolve(): array
    {
        $ids = $this->resolveIds();
        $prayers = Prayer::query()->published()->with('category')->whereKey(array_filter($ids))->get()->keyBy('id');

        return array_map(fn (?int $id) => $id ? $prayers->get($id) : null, $ids);
    }

    /**
     * IDs escolhidos (configuração do painel → heurística). Cache curto: a escolha é a mesma para
     * todos; o que muda por pessoa (bloqueio Sancta+) é calculado depois, no PrayerResource.
     *
     * @return array{morning: ?int, afternoon: ?int, night: ?int}
     */
    public function resolveIds(): array
    {
        $configured = array_map(fn (string $key) => $this->configuredId($key), self::SETTINGS);

        return Cache::remember('home.period_prayers.'.md5(json_encode($configured)), 300, function () use ($configured): array {
            $published = Prayer::query()->published()->with('category:id,slug,name')
                ->get(['id', 'slug', 'title', 'category_id', 'is_premium', 'is_featured', 'sort_order']);

            $taken = [];
            $result = [];
            foreach (self::KEYWORDS as $period => $words) {
                $chosen = $configured[$period] !== null && $published->contains('id', $configured[$period])
                    ? $configured[$period]
                    : $this->guess($published, $words, $taken);
                $result[$period] = $chosen;
                if ($chosen !== null) {
                    $taken[] = $chosen;
                }
            }

            return $result;
        });
    }

    private function configuredId(string $key): ?int
    {
        $value = $this->settings->get($key);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** Primeiro pelo título/slug, depois pela categoria; prefere gratuitas, destaques e a ordem do painel. */
    private function guess(Collection $prayers, array $words, array $taken): ?int
    {
        $candidates = $prayers->reject(fn (Prayer $p) => in_array($p->id, $taken, true))
            ->sortBy([['is_premium', 'asc'], ['is_featured', 'desc'], ['sort_order', 'asc'], ['id', 'asc']])
            ->values();

        foreach ([fn (Prayer $p) => $p->title.' '.$p->slug, fn (Prayer $p) => ($p->category?->name ?? '').' '.($p->category?->slug ?? '')] as $haystack) {
            foreach ($words as $word) {
                $match = $candidates->first(fn (Prayer $p) => str_contains(self::plain($haystack($p)), $word));
                if ($match) {
                    return $match->id;
                }
            }
        }

        return null;
    }

    /** Minúsculas, sem acentos e com hífens do slug virando espaço ("Oração da manhã" = "oracao-da-manha"). */
    public static function plain(string $text): string
    {
        return str_replace('-', ' ', Str::lower(Str::ascii($text)));
    }
}
