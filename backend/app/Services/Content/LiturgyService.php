<?php

namespace App\Services\Content;

use App\Models\LiturgyDay;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Liturgia diária: busca na API externa, normaliza e guarda no banco.
 * Dias editados manualmente no painel (is_locked) nunca são sobrescritos.
 */
class LiturgyService
{
    public function forDate(CarbonInterface $date): ?LiturgyDay
    {
        $day = LiturgyDay::query()->whereDate('date', $date->toDateString())->first();

        $stale = $day && ! $day->is_locked && $day->fetched_at && $day->fetched_at->lt(now()->subHours(12)) && $date->isToday();

        if (! $day || $stale) {
            $day = $this->fetch($date) ?? $day;
        }

        return $day;
    }

    public function fetch(CarbonInterface $date): ?LiturgyDay
    {
        $existing = LiturgyDay::query()->whereDate('date', $date->toDateString())->first();
        if ($existing?->is_locked) {
            return $existing;
        }

        try {
            $response = Http::timeout(10)->retry(1, 300, throw: false)->acceptJson()
                ->get(rtrim((string) config('sancta.liturgy.api_url'), '/').'/', [
                    'dia' => $date->day,
                    'mes' => $date->month,
                    'ano' => $date->year,
                ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $data = $response->successful() ? $response->json() : null;
        if (! is_array($data) || empty($data['liturgia'])) {
            return null;
        }

        $reading = fn (?array $r, string $label) => $r ? [
            'label' => $label,
            'reference' => $r['referencia'] ?? null,
            'title' => $r['titulo'] ?? null,
            'refrain' => $r['refrao'] ?? null,
            'text' => $this->cleanText($r['texto'] ?? ''),
        ] : null;

        $readings = array_values(array_filter([
            $reading($data['primeiraLeitura'] ?? null, 'Primeira leitura'),
            $reading($data['salmo'] ?? null, 'Salmo responsorial'),
            is_array($data['segundaLeitura'] ?? null) ? $reading($data['segundaLeitura'], 'Segunda leitura') : null,
            $reading($data['evangelho'] ?? null, 'Evangelho'),
        ]));

        return LiturgyDay::query()->updateOrCreate(['date' => $date->toDateString()], [
            'title' => mb_substr((string) $data['liturgia'], 0, 255),
            'color' => $data['cor'] ?? null,
            'readings' => $readings,
            'prayers' => array_filter([
                'collect' => $data['dia'] ?? null,
                'offerings' => $data['oferendas'] ?? null,
                'communion' => $data['comunhao'] ?? null,
            ]),
            'source' => 'api',
            'fetched_at' => now(),
        ]);
    }

    private function cleanText(string $text): string
    {
        return trim(strip_tags($text));
    }
}
