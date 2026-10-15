<?php

namespace App\Services\Content;

use App\Models\AiExplanation;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Explicação de versículos com IA (recurso Sancta+).
 * A chave da API fica SOMENTE no servidor (no app antigo ela estava exposta no APK).
 * Respostas são cacheadas por versículo: o custo cai a cada novo usuário.
 */
class AiExplanationService
{
    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key'));
    }

    public function explain(string $reference, string $text): AiExplanation
    {
        $text = trim(mb_substr($text, 0, 3000));
        $key = hash('sha256', $reference.'|'.$text);

        $cached = AiExplanation::query()->where('cache_key', $key)->first();
        if ($cached) {
            $cached->increment('hits');

            return $cached;
        }

        if (! $this->isConfigured()) {
            throw new RuntimeException('Recurso de IA indisponível no momento.');
        }

        $prompt = 'Você é um catequista católico, fiel ao Magistério da Igreja e ao Catecismo. '
            ."Explique de forma acolhedora, simples e fundamentada o trecho bíblico {$reference}:\n\n\"{$text}\"\n\n"
            .'Responda em português do Brasil, em no máximo 2 parágrafos curtos, para leitura no celular. '
            .'Se apropriado, cite um número do Catecismo da Igreja Católica. Não invente fatos.';

        $model = config('services.gemini.model', 'gemini-2.5-flash');
        $response = Http::timeout(30)
            ->withHeaders(['x-goog-api-key' => config('services.gemini.api_key')])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 600],
            ]);

        $content = trim((string) $response->json('candidates.0.content.parts.0.text'));
        if ($response->failed() || $content === '') {
            throw new RuntimeException('Não foi possível gerar a explicação agora. Tente novamente.');
        }

        return AiExplanation::query()->create([
            'cache_key' => $key,
            'reference' => mb_substr($reference, 0, 120),
            'content' => $content,
            'model' => $model,
            'hits' => 1,
        ]);
    }
}
