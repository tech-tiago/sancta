<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitização de HTML (anti-XSS) e conversão para blocos simples consumidos pelo app.
 * Todo conteúdo rico vindo do painel ou de feeds RSS passa por aqui antes de ser salvo.
 */
final class Html
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowElement('a', ['href', 'title'])
                ->allowElement('img', ['src', 'alt', 'title', 'width', 'height'])
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowMediaSchemes(['https'])
                ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
                ->dropElement('script')
                ->dropElement('style')
                ->dropElement('iframe')
                ->dropElement('form')
                ->withMaxInputLength(500_000)
        );

        return self::$sanitizer->sanitize($html);
    }

    /** Texto puro (para resumos, notificações e meta descriptions). */
    public static function toText(?string $html, int $limit = 0): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n{3,}/", "\n\n", $text)));

        return $limit > 0 ? mb_strimwidth($text, 0, $limit, '…') : $text;
    }

    /**
     * Converte HTML sanitizado em blocos simples para o app Flutter renderizar
     * sem precisar de um motor de HTML (app mais leve e sem risco de XSS em WebView).
     *
     * @return list<array{type: string, text?: string, items?: list<string>, level?: int, src?: string}>
     */
    public static function toBlocks(?string $html): array
    {
        $html = self::sanitize($html);
        if ($html === '') {
            return [];
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="root">'.$html.'</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('root');
        if (! $root) {
            return [['type' => 'paragraph', 'text' => self::toText($html)]];
        }

        $blocks = [];
        foreach ($root->childNodes as $node) {
            self::nodeToBlocks($node, $blocks);
        }

        return array_values(array_filter($blocks, fn ($b) => ($b['text'] ?? '') !== '' || ! empty($b['items']) || ! empty($b['src'])));
    }

    private static function nodeToBlocks(DOMNode $node, array &$blocks): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = trim($node->textContent);
            if ($text !== '') {
                $blocks[] = ['type' => 'paragraph', 'text' => $text];
            }

            return;
        }

        if (! $node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);
        $text = trim(preg_replace('/\s+/u', ' ', $node->textContent));

        match (true) {
            in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) => $blocks[] = ['type' => 'heading', 'level' => (int) substr($tag, 1), 'text' => $text],
            $tag === 'blockquote' => $blocks[] = ['type' => 'quote', 'text' => $text],
            in_array($tag, ['ul', 'ol'], true) => $blocks[] = [
                'type' => $tag === 'ol' ? 'ordered_list' : 'list',
                'items' => array_values(array_filter(array_map(
                    fn ($li) => trim(preg_replace('/\s+/u', ' ', $li->textContent)),
                    iterator_to_array($node->getElementsByTagName('li'))
                ))),
            ],
            $tag === 'img' => $blocks[] = ['type' => 'image', 'src' => $node->getAttribute('src')],
            $tag === 'hr' => $blocks[] = ['type' => 'divider', 'text' => '—'],
            in_array($tag, ['div', 'section', 'article'], true) => array_map(fn ($child) => self::nodeToBlocks($child, $blocks), iterator_to_array($node->childNodes)),
            default => $blocks[] = ['type' => 'paragraph', 'text' => $text],
        };
    }
}
