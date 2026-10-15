<?php

namespace App\Services\Content;

use App\Models\NewsArticle;
use App\Models\NewsSource;
use App\Support\Html;
use App\Support\NetworkGuard;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * Importa notícias de feeds RSS cadastrados no painel (ex.: Vatican News).
 * Segurança: bloqueio de SSRF (somente URLs públicas), limite de tamanho e tempo,
 * XML sem acesso à rede/entidades externas (XXE) e HTML sanitizado.
 */
class NewsFetcher
{
    public function fetch(NewsSource $source): int
    {
        try {
            $count = $this->import($source);
            $source->forceFill(['last_fetched_at' => now(), 'last_error' => null])->save();

            return $count;
        } catch (Throwable $e) {
            $source->forceFill(['last_fetched_at' => now(), 'last_error' => Str::limit($e->getMessage(), 480)])->save();

            return 0;
        }
    }

    private function import(NewsSource $source): int
    {
        if ($source->type !== 'rss' || ! NetworkGuard::isPublicHttpUrl($source->url)) {
            throw new RuntimeException('URL do feed inválida ou não permitida.');
        }

        $response = Http::timeout(config('sancta.news.timeout_seconds', 15))
            ->withHeaders(['User-Agent' => 'SanctaBot/1.0 (+https://sancta.app.br)'])
            ->withOptions(['stream' => true, 'allow_redirects' => [
                'max' => 3,
                'protocols' => ['https', 'http'],
                // Cada salto do redirecionamento passa pela mesma checagem anti-SSRF da URL original
                // (um feed público não pode redirecionar para metadados da nuvem, localhost ou rede privada).
                'on_redirect' => function ($request, $response, $uri): void {
                    if (! NetworkGuard::isPublicHttpUrl((string) $uri)) {
                        throw new RuntimeException('Redirecionamento do feed para endereço não permitido.');
                    }
                },
            ]])
            ->get($source->url);

        if ($response->failed()) {
            throw new RuntimeException("Feed respondeu HTTP {$response->status()}.");
        }

        // Lê em blocos e para assim que passar do limite (um feed gigante nunca vai inteiro para a memória).
        $body = $this->readLimited($response, (int) config('sancta.news.max_feed_bytes'));
        if ($body === null) {
            throw new RuntimeException('Feed excede o tamanho máximo permitido.');
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $xml || ! isset($xml->channel->item)) {
            throw new RuntimeException('Conteúdo não é um RSS válido.');
        }

        $imported = 0;
        foreach ($xml->channel->item as $index => $item) {
            if ($imported >= $source->max_items) {
                break;
            }

            $link = trim((string) $item->link);
            if (! filter_var($link, FILTER_VALIDATE_URL) || ! str_starts_with($link, 'http')) {
                continue;
            }

            $hash = hash('sha256', $link);
            if (NewsArticle::query()->where('url_hash', $hash)->exists()) {
                continue;
            }

            $description = (string) $item->description;
            $content = $item->children('content', true)->encoded ?? null;

            NewsArticle::query()->create([
                'news_source_id' => $source->id,
                'title' => Str::limit(html_entity_decode(trim((string) $item->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 250, ''),
                'summary' => Html::toText($description, 400),
                'content' => $content ? Html::sanitize((string) $content) : null,
                'image_url' => $this->image($item, $description),
                'url' => $link,
                'author' => Str::limit(trim((string) ($item->children('dc', true)->creator ?? '')), 110, '') ?: null,
                'published_at' => $this->date((string) $item->pubDate),
                'status' => 'published',
            ]);
            $imported++;
        }

        return $imported;
    }

    private function image(SimpleXMLElement $item, string $description): ?string
    {
        $candidates = [];

        $media = $item->children('media', true);
        if (isset($media->content)) {
            $candidates[] = (string) $media->content->attributes()->url;
        }
        if (isset($media->thumbnail)) {
            $candidates[] = (string) $media->thumbnail->attributes()->url;
        }
        if (isset($item->enclosure)) {
            $candidates[] = (string) $item->enclosure->attributes()->url;
        }
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $m)) {
            $candidates[] = $m[1];
        }

        foreach ($candidates as $url) {
            if (str_starts_with($url, 'https://') && strlen($url) <= 500) {
                return $url;
            }
        }

        return null;
    }

    private function date(string $value): Carbon
    {
        try {
            $date = Carbon::parse($value);

            return $date->isFuture() ? now() : $date;
        } catch (Throwable) {
            return now();
        }
    }

    /** Lê o corpo da resposta sem passar do limite (null = excedeu). */
    private function readLimited(Response $response, int $max): ?string
    {
        $length = $response->header('Content-Length');
        if ($length !== '' && is_numeric($length) && (int) $length > $max) {
            return null;
        }

        $stream = $response->toPsrResponse()->getBody();
        $body = '';
        while (! $stream->eof() && strlen($body) <= $max) {
            $chunk = $stream->read(65536);
            if ($chunk === '') {
                break;
            }
            $body .= $chunk;
        }

        return strlen($body) > $max ? null : $body;
    }
}
