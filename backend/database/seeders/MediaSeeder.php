<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\LiveChannel;
use App\Models\NewsSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/** Fontes de notícias, canais ao vivo herdados do app antigo e um aviso de boas-vindas. */
class MediaSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['Vatican News', 'https://www.vaticannews.va/pt.rss.xml', 'https://www.vaticannews.va/pt.html', true],
            ['CNBB', 'https://www.cnbb.org.br/feed/', 'https://www.cnbb.org.br', false],
            ['Canção Nova Notícias', 'https://noticias.cancaonova.com/feed/', 'https://noticias.cancaonova.com', false],
        ];

        foreach ($sources as [$name, $url, $website, $active]) {
            NewsSource::query()->firstOrCreate(['name' => $name], [
                'type' => 'rss', 'url' => $url, 'website' => $website, 'is_active' => $active,
                'fetch_interval_minutes' => 60, 'max_items' => 30,
            ]);
        }

        $channels = [
            // O ID do canal (UC…) para a detecção automática é cadastrado no painel (Mídia → Ao vivo).
            ['TV Canção Nova', 'Missas, pregações e orações 24 horas por dia.', 'live', 'https://www.youtube.com/watch?v=NuJ2gazIEs8', 'https://tv.cancaonova.com/', 'tv_cancao_nova.png', 'canal'],
            ['TV Aparecida', 'A TV de Nossa Senhora: missas, novenas e programação católica.', 'live', 'https://www.youtube.com/watch?v=4CAmwaFJo6k', 'https://www.a12.com/tv/tv-ao-vivo', 'tv_aparecida.png', 'canal'],
            ['Frei Gilson', 'Meditação da Palavra e orações.', 'playlist', 'https://www.youtube.com/playlist?list=PLWM-D0VS9_EwwQSQnBjGjp0G11vCsYTiU', null, null, 'oracao'],
        ];

        foreach ($channels as $i => [$name, $description, $type, $url, $fallback, $logo, $category]) {
            $logoPath = null;
            $source = database_path("data/images/{$logo}");
            if ($logo && is_file($source)) {
                $logoPath = "live/{$logo}";
                Storage::disk('public')->put($logoPath, file_get_contents($source));
            }

            LiveChannel::query()->firstOrCreate(['name' => $name], [
                'description' => $description, 'type' => $type, 'category' => $category, 'youtube_url' => $url, 'fallback_url' => $fallback,
                'logo' => $logoPath, 'is_active' => true, 'sort_order' => $i,
            ]);
        }

        Announcement::query()->firstOrCreate(['title' => 'Bem-vindo ao novo Sancta! ✨'], [
            'body' => 'Nosso app foi totalmente renovado: orações guiadas, jornadas, novenas, Bíblia com anotações e muito mais. Comece hoje a Novena de Nossa Senhora Aparecida!',
            'kind' => 'popup',
            'category' => 'update',
            'cta_label' => 'Começar a novena',
            'cta_url' => 'sancta://jornada/novena-nossa-senhora-aparecida',
            'audience' => 'all',
            'platform' => 'app',
            'frequency' => 'once',
            'priority' => 10,
            'dismissible' => true,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);
    }
}
