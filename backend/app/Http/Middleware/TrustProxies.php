<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Proxies confiáveis (Cloudflare, balanceador) lidos de config('sancta.security.trusted_proxies').
 *
 * Substitui o TrustProxies do framework porque a lista vinha de env() no bootstrap/app.php, que roda
 * antes de o .env ser carregado: nenhum proxy era confiado e, atrás da Cloudflare, todo visitante
 * apareceria com o IP dela (limite de tentativas de login compartilhado, restrição de IP do painel
 * comparando o endereço errado).
 *
 * Do proxy vêm só o IP do visitante e o protocolo. Host, porta e prefixo encaminhados não: a
 * Cloudflare repassa esses cabeçalhos do jeito que o visitante os enviou.
 */
class TrustProxies extends Middleware
{
    protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO;

    protected function proxies(): array
    {
        return self::resolve((string) config('sancta.security.trusted_proxies'));
    }

    /** "cloudflare,10.0.0.1" → faixas da Cloudflare + 10.0.0.1. Entradas inválidas são ignoradas. */
    public static function resolve(string $setting): array
    {
        $proxies = [];
        foreach (array_filter(array_map('trim', explode(',', $setting))) as $entry) {
            if (strtolower($entry) === 'cloudflare') {
                array_push($proxies, ...self::cloudflareRanges());
            } elseif (self::isIpOrCidr($entry)) {
                $proxies[] = $entry;
            }
        }

        return array_values(array_unique($proxies));
    }

    /** A lista mantida pelo servidor, se existir e estiver íntegra; senão, a que acompanha o código. */
    private static function cloudflareRanges(): array
    {
        $file = (string) config('sancta.security.cloudflare_ips_file');

        if ($file !== '' && is_readable($file)) {
            $lines = array_values(array_filter(array_map('trim', file($file, FILE_IGNORE_NEW_LINES) ?: [])));
            $invalid = array_filter($lines, fn (string $line) => ! self::isIpOrCidr($line));

            if (count($lines) >= 10 && $invalid === []) {
                return $lines;
            }
        }

        return config('sancta.security.cloudflare_ips', []);
    }

    private static function isIpOrCidr(string $value): bool
    {
        [$ip, $bits] = array_pad(explode('/', $value, 2), 2, null);

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return $bits === null || (ctype_digit($bits) && (int) $bits <= (str_contains($ip, ':') ? 128 : 32));
    }
}
