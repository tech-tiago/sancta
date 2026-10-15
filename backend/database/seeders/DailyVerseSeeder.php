<?php

namespace Database\Seeders;

use App\Models\BibleBook;
use App\Models\BibleVerse;
use App\Models\DailyVerse;
use Illuminate\Database\Seeder;

/**
 * Versículo do dia para os próximos 60 dias (texto obtido da própria Bíblia importada).
 * O administrador pode editar/agendar outros no painel.
 */
class DailyVerseSeeder extends Seeder
{
    /** [slug do livro, capítulo, versículo inicial, versículo final, título da reflexão, reflexão] */
    private const VERSES = [
        ['1-joao', 4, 16, 16, 'Permanecer no amor', 'Deus não é uma ideia distante: é amor que se aproxima. Hoje, permaneça nesse amor em cada gesto simples.'],
        ['salmos', 118, 105, 105, 'Uma luz para o caminho', 'A Palavra não ilumina toda a estrada de uma vez — ilumina o próximo passo. Confie.'],
        ['mateus', 11, 28, 28, 'Vinde a mim', 'Jesus não pede que você chegue descansado. Ele convida justamente os cansados.'],
        ['filipenses', 4, 13, 13, 'Força que vem do alto', 'A força cristã não é autossuficiência: é confiança n\'Aquele que sustenta.'],
        ['isaias', 41, 10, 10, 'Não temas', 'O medo perde força quando lembramos quem caminha conosco.'],
        ['joao', 14, 27, 27, 'A paz de Cristo', 'A paz que Jesus dá não depende das circunstâncias. Peça-a com confiança.'],
        ['romanos', 8, 28, 28, 'Tudo concorre para o bem', 'Mesmo o que não entendemos pode ser tecido por Deus para o nosso bem.'],
        ['salmos', 22, 1, 1, 'O Senhor é meu pastor', 'Quem é conduzido pelo Bom Pastor não precisa carregar sozinho o peso do caminho.'],
        ['proverbios', 3, 5, 6, 'Confiar de todo o coração', 'Entregar a Deus os planos é o primeiro passo para que eles tenham rumo.'],
        ['jeremias', 29, 11, 11, 'Projetos de paz', 'Deus tem para você pensamentos de paz, não de desgraça.'],
        ['mateus', 6, 33, 33, 'Buscar primeiro o Reino', 'Quando Deus ocupa o primeiro lugar, todo o resto encontra o seu lugar.'],
        ['2-corintios', 12, 9, 9, 'A graça basta', 'Na fraqueza, o poder de Deus se manifesta plenamente.'],
        ['lucas', 1, 38, 38, 'Faça-se', 'O "sim" de Maria mudou a história. Qual "sim" Deus espera de você hoje?'],
        ['salmos', 45, 11, 11, 'Aquietai-vos', 'Pare por um minuto. No silêncio, Deus se revela.'],
        ['joao', 15, 5, 5, 'Permanecer na videira', 'Sem Ele nada podemos; com Ele, damos muito fruto.'],
    ];

    public function run(): void
    {
        $date = now()->startOfDay();

        for ($day = 0; $day < 60; $day++) {
            [$slug, $chapter, $from, $to, $title, $reflection] = self::VERSES[$day % count(self::VERSES)];
            $book = BibleBook::query()->where('slug', $slug)->first();
            if (! $book) {
                continue;
            }

            $text = BibleVerse::query()
                ->where('bible_book_id', $book->id)
                ->where('chapter', $chapter)
                ->whereBetween('verse', [$from, $to])
                ->orderBy('verse')
                ->pluck('text')
                ->implode(' ');

            if ($text === '') {
                continue;
            }

            $reference = "{$book->abbreviation} {$chapter},{$from}".($to !== $from ? "-{$to}" : '');

            DailyVerse::query()->updateOrCreate(['date' => $date->copy()->addDays($day)->toDateString()], [
                'reference' => $reference,
                'text' => $text,
                'reflection_title' => $title,
                'reflection' => $reflection,
                'reflection_is_premium' => $day % 3 !== 0, // alguns dias com reflexão aberta, outros exclusivos
            ]);
        }
    }
}
