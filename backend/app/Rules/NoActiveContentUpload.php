<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Recusa arquivos que o navegador pode executar quando abertos diretamente
 * (SVG, HTML, XML, JavaScript). Uploads do painel vão para o disco público e são
 * servidos do mesmo domínio do painel — um SVG com script viraria XSS armazenado.
 * Checa o tipo real (conteúdo) e a extensão.
 */
class NoActiveContentUpload implements ValidationRule
{
    private const BLOCKED_MIME = [
        'image/svg+xml', 'text/html', 'application/xhtml+xml', 'text/xml', 'application/xml',
        'text/javascript', 'application/javascript', 'application/x-httpd-php', 'text/x-php',
    ];

    private const BLOCKED_EXTENSIONS = ['svg', 'svgz', 'html', 'htm', 'xhtml', 'xml', 'js', 'mjs', 'php', 'phtml', 'phar'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $mime = strtolower((string) $value->getMimeType());
        $extensions = array_map('strtolower', array_filter([
            $value->getClientOriginalExtension(),
            $value->guessExtension(),
            pathinfo((string) $value->getFilename(), PATHINFO_EXTENSION),
        ]));

        if (in_array($mime, self::BLOCKED_MIME, true) || array_intersect($extensions, self::BLOCKED_EXTENSIONS) !== []) {
            $fail('Este tipo de arquivo não é permitido. Envie uma imagem JPG, PNG ou WebP (ou o formato pedido no campo).');
        }
    }
}
