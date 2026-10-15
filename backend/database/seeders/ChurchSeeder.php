<?php

namespace Database\Seeders;

use App\Enums\ChurchType;
use App\Enums\ScheduleType;
use App\Models\Church;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Importa as igrejas do app antigo (horarios.json), convertendo os horários
 * em texto livre ("19:00 (Quinta-feira)") para horários estruturados.
 */
class ChurchSeeder extends Seeder
{
    private const WEEKDAYS = [
        'domingo' => 0, 'segunda' => 1, 'terça' => 2, 'terca' => 2, 'quarta' => 3,
        'quinta' => 4, 'sexta' => 5, 'sábado' => 6, 'sabado' => 6,
    ];

    public function run(): void
    {
        $path = database_path('data/igrejas.json');
        if (! is_file($path)) {
            return;
        }

        $items = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($items as $item) {
            $name = Str::title(Str::lower($item['name']));
            $name = str_replace([' Da ', ' De ', ' Do ', ' E ', "Sant'ana"], [' da ', ' de ', ' do ', ' e ', "Sant'Ana"], $name);

            $church = Church::query()->firstOrCreate(
                ['name' => $name],
                [
                    'type' => str_starts_with(Str::upper($item['name']), 'PARÓQUIA') ? ChurchType::Paroquia : ChurchType::Comunidade,
                    'description' => $item['description'] ?? null,
                    'address' => $item['address'] ?? null,
                    'city' => 'Porto Seguro',
                    'state' => 'BA',
                    'country' => 'Brasil',
                    'latitude' => $item['location']['latitude'] ?? null,
                    'longitude' => $item['location']['longitude'] ?? null,
                    'phone' => $item['phone'] ?? null,
                    'email' => $item['email'] ?? null,
                    'instagram' => $item['instagram'] ?? null,
                    'is_verified' => (bool) ($item['is_verified'] ?? false),
                    'status' => 'published',
                ]
            );

            if ($church->schedules()->exists()) {
                continue;
            }

            $groups = [
                ScheduleType::Missa->value => $item['mass_times'] ?? [],
                ScheduleType::Adoracao->value => $item['adoration_times'] ?? [],
                ScheduleType::Confissao->value => $item['confession_times'] ?? [],
                ScheduleType::Secretaria->value => $item['secretary_hours'] ?? [],
            ];

            $order = 0;
            foreach ($groups as $type => $lines) {
                foreach ($lines as $line) {
                    foreach ($this->parse($line) as $schedule) {
                        $church->schedules()->create($schedule + ['type' => $type, 'sort_order' => $order++]);
                    }
                }
            }
        }
    }

    /**
     * "19:00 (Quinta-feira)"                 → [weekday 4, 19:00]
     * "08:00 às 12:00 (Segunda a Sexta)"     → 5 registros (seg a sex)
     * "Antes da missa, 18:00 (Quinta-feira)" → nota "Antes da missa"
     * "Atendimento na matriz"                → texto livre (weekday null)
     */
    private function parse(string $line): array
    {
        $line = trim($line);
        $notes = null;

        if (str_contains($line, ',')) {
            [$notes, $line] = array_map('trim', explode(',', $line, 2));
        }

        if (! preg_match('/(\d{1,2}:\d{2})(?:\s*às\s*(\d{1,2}:\d{2}))?\s*\(([^)]+)\)/u', $line, $m)) {
            return [['weekday' => null, 'starts_at' => null, 'ends_at' => null, 'notes' => Str::limit(trim(($notes ? "{$notes}, " : '').$line), 190)]];
        }

        $start = $m[1];
        $end = $m[2] ?: null;
        $days = $this->weekdays($m[3]);

        return array_map(fn ($day) => [
            'weekday' => $day,
            'starts_at' => $start,
            'ends_at' => $end,
            'notes' => $notes,
        ], $days ?: [null]);
    }

    private function weekdays(string $label): array
    {
        $label = Str::lower($label);

        if (preg_match('/(\w+)\s+a\s+(\w+)/u', $label, $m)) {
            $from = $this->weekday($m[1]);
            $to = $this->weekday($m[2]);
            if ($from !== null && $to !== null && $from <= $to) {
                return range($from, $to);
            }
        }

        $day = $this->weekday($label);

        return $day === null ? [] : [$day];
    }

    private function weekday(string $value): ?int
    {
        foreach (self::WEEKDAYS as $name => $index) {
            if (str_starts_with(Str::lower(trim($value)), $name)) {
                return $index;
            }
        }

        return null;
    }
}
