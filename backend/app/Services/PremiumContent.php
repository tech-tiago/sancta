<?php

namespace App\Services;

use App\Models\Testimonial;
use Illuminate\Support\Str;

/**
 * Conteúdo do paywall Sancta+ (app e página /planos), editável em
 * Painel → Configurações → aba Sancta+.
 *
 * Compatibilidade: `premium.benefits` é guardado como lista estruturada
 * [{icon, title, description}], mas versões antigas guardavam lista de strings —
 * tudo passa por normalizeBenefits(). Na API, `benefits` continua sendo lista de
 * strings (o app atual lê assim) e `benefit_items` traz a versão estruturada.
 */
class PremiumContent
{
    /** Ícones permitidos nos benefícios (chave => rótulo no painel). App e site mapeiam a chave para o próprio ícone. */
    public const ICONS = [
        'headphones' => 'Fones (orações em áudio)',
        'flame' => 'Chama (jornadas, sequência)',
        'sun' => 'Sol (reflexão diária)',
        'sparkles' => 'Brilho (IA, novidades)',
        'book' => 'Livro (Bíblia, leitura)',
        'calendar' => 'Calendário (eventos)',
        'heart' => 'Coração (apoio, amor)',
        'cross' => 'Cruz (fé, oração)',
        'chalice' => 'Cálice (liturgia, missa)',
        'play' => 'Play (vídeos, ao vivo)',
        'moon' => 'Lua (oração da noite)',
        'users' => 'Pessoas (comunidade)',
        'shield' => 'Escudo (sem anúncios, segurança)',
        'star' => 'Estrela (destaque)',
    ];

    public const DEFAULT_ICON = 'star';

    public function __construct(private readonly Settings $settings) {}

    /** Bloco `premium` de /api/v1/config. */
    public function toArray(): array
    {
        $items = $this->benefitItems();

        return [
            'name' => (string) $this->settings->get('premium.name'),
            'headline' => (string) $this->settings->get('premium.headline'),
            'subheadline' => $this->text('premium.subheadline'),
            // Compatibilidade com o app atual: lista de strings.
            'benefits' => array_column($items, 'title'),
            'benefit_items' => $items,
            'social_proof' => $this->text('premium.social_proof'),
            'guarantee' => $this->text('premium.guarantee'),
            'faq' => $this->faq(),
            'testimonials' => $this->testimonials(),
        ];
    }

    /** @return list<array{icon: string, title: string, description: ?string}> */
    public function benefitItems(): array
    {
        return self::normalizeBenefits($this->settings->get('premium.benefits'));
    }

    /** @return list<array{question: string, answer: string}> */
    public function faq(): array
    {
        $out = [];
        foreach ((array) $this->settings->get('premium.faq') as $item) {
            if (! is_array($item)) {
                continue;
            }
            $question = trim(strip_tags((string) ($item['question'] ?? '')));
            $answer = trim(strip_tags((string) ($item['answer'] ?? '')));
            if ($question !== '' && $answer !== '') {
                $out[] = ['question' => Str::limit($question, 200, ''), 'answer' => Str::limit($answer, 1000, '')];
            }
        }

        return $out;
    }

    /**
     * Depoimentos ativos para o paywall — só o nome exibido, o texto e a nota
     * (sem cidade ou qualquer outro dado pessoal).
     *
     * @return list<array{author: string, content: string, rating: int}>
     */
    public function testimonials(int $limit = 10): array
    {
        return Testimonial::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($limit)
            ->get(['author', 'content', 'rating'])
            ->map(fn (Testimonial $t) => [
                'author' => (string) $t->author,
                'content' => trim(strip_tags((string) $t->content)),
                'rating' => max(1, min(5, (int) $t->rating)),
            ])
            ->values()
            ->all();
    }

    /**
     * Aceita o formato antigo (lista de strings) e o novo (lista de {icon, title, description}).
     *
     * @return list<array{icon: string, title: string, description: ?string}>
     */
    public static function normalizeBenefits(mixed $value): array
    {
        $out = [];
        foreach ((array) $value as $item) {
            if (is_string($item)) {
                $item = ['title' => $item, 'icon' => self::guessIcon($item), 'description' => null];
            }
            if (! is_array($item)) {
                continue;
            }

            $title = trim(strip_tags((string) ($item['title'] ?? '')));
            if ($title === '') {
                continue;
            }

            $icon = (string) ($item['icon'] ?? '');
            $description = trim(strip_tags((string) ($item['description'] ?? '')));

            $out[] = [
                'icon' => array_key_exists($icon, self::ICONS) ? $icon : self::DEFAULT_ICON,
                'title' => Str::limit($title, 120, ''),
                'description' => $description === '' ? null : Str::limit($description, 300, ''),
            ];
        }

        return $out;
    }

    /** Ícone sugerido para um benefício antigo (só texto), pelas palavras-chave. */
    public static function guessIcon(string $title): string
    {
        $t = mb_strtolower($title);

        return match (true) {
            str_contains($t, 'áudio') || str_contains($t, 'audio') || str_contains($t, 'guiad') => 'headphones',
            str_contains($t, 'reflex') || str_contains($t, 'evangelho') || str_contains($t, 'homilia') => 'sun',
            str_contains($t, ' ia') || str_contains($t, 'inteligência') || str_contains($t, 'explica') => 'sparkles',
            str_contains($t, 'bíblia') || str_contains($t, 'biblia') || str_contains($t, 'leitura') => 'book',
            str_contains($t, 'evento') || str_contains($t, 'retiro') => 'calendar',
            str_contains($t, 'jornada') || str_contains($t, 'novena') => 'flame',
            str_contains($t, 'anúncio') || str_contains($t, 'apoia') || str_contains($t, 'evangeliza') => 'heart',
            default => self::DEFAULT_ICON,
        };
    }

    private function text(string $key): ?string
    {
        $value = trim(strip_tags((string) $this->settings->get($key)));

        return $value === '' ? null : $value;
    }
}
