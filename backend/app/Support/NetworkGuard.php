<?php

namespace App\Support;

/**
 * Proteção contra SSRF: só permite requisições de saída (ex.: leitura de RSS)
 * para URLs http(s) públicas — bloqueia localhost, redes privadas e metadados de nuvem.
 */
final class NetworkGuard
{
    public static function isPublicHttpUrl(?string $url): bool
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443, 8080, 8443], true)) {
            return false;
        }

        $host = trim($parts['host'], '[]');
        if (in_array(strtolower($host), ['localhost', 'metadata.google.internal'], true)) {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
