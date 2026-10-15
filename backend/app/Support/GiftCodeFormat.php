<?php

namespace App\Support;

/**
 * Códigos de presente no formato SANC-XXXX-XXXX-XXXX.
 *
 * - 12 caracteres do alfabeto Crockford base32 = 60 bits vindos de random_bytes().
 * - Na leitura, aceita minúsculas, espaços/hífens, prefixo opcional e as confusões
 *   comuns do Crockford (O→0, I/L→1).
 * - No banco fica só o HMAC-SHA256 (chave = APP_KEY) e os 4 últimos caracteres.
 *   Com APP_PREVIOUS_KEYS, códigos antigos continuam válidos após trocar a APP_KEY.
 */
final class GiftCodeFormat
{
    public const PREFIX = 'SANC';

    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const LENGTH = 12;

    /** Gera um código novo (texto em claro — mostre/envie e descarte). */
    public static function generate(): string
    {
        // 8 bytes = 64 bits; usamos 60 (12 × 5 bits).
        $bits = '';
        foreach (str_split(random_bytes(8)) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $chars = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $chars .= self::ALPHABET[bindec(substr($bits, $i * 5, 5))];
        }

        return self::format($chars);
    }

    /** "ABCD1234EFGH" → "SANC-ABCD-1234-EFGH". */
    public static function format(string $canonical): string
    {
        return self::PREFIX.'-'.implode('-', str_split($canonical, 4));
    }

    /** Normaliza o que a pessoa digitou para os 12 caracteres canônicos (null = formato inválido). */
    public static function canonical(?string $input): ?string
    {
        $value = strtoupper((string) $input);
        $value = preg_replace('/[\s\-_.]+/', '', $value) ?? '';

        if (str_starts_with($value, self::PREFIX) && strlen($value) === strlen(self::PREFIX) + self::LENGTH) {
            $value = substr($value, strlen(self::PREFIX));
        }

        $value = strtr($value, ['O' => '0', 'I' => '1', 'L' => '1']);

        if (strlen($value) !== self::LENGTH || strspn($value, self::ALPHABET) !== self::LENGTH) {
            return null;
        }

        return $value;
    }

    /** HMAC do código canônico com a APP_KEY atual. */
    public static function hash(string $canonical): string
    {
        return hash_hmac('sha256', self::PREFIX.'-'.$canonical, (string) config('app.key'));
    }

    /**
     * Hashes candidatos (APP_KEY atual + anteriores) para a busca no banco.
     *
     * @return list<string>
     */
    public static function hashCandidates(string $canonical): array
    {
        $keys = array_filter([(string) config('app.key'), ...(array) config('app.previous_keys', [])]);

        return array_values(array_unique(array_map(
            fn (string $key) => hash_hmac('sha256', self::PREFIX.'-'.$canonical, $key),
            $keys,
        )));
    }

    /** Exibição segura: "SANC-••••-••••-AB12". */
    public static function masked(string $last4): string
    {
        return self::PREFIX.'-••••-••••-'.$last4;
    }
}
