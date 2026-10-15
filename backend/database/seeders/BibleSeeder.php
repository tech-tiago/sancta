<?php

namespace Database\Seeders;

use App\Models\BibleVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Importa a Bíblia (73 livros, ~35 mil versículos) do JSON herdado do app antigo.
 *
 * ATENÇÃO (licença): o texto é da tradução "Ave-Maria". Para uso comercial
 * (assinaturas), obtenha autorização da Editora Ave-Maria ou troque por uma
 * tradução licenciada/domínio público — basta importar outra versão aqui.
 */
class BibleSeeder extends Seeder
{
    /** [nome exibido, abreviação, slug] na ordem do cânon católico. */
    private const BOOKS = [
        'AT' => [
            ['Gênesis', 'Gn', 'genesis'], ['Êxodo', 'Ex', 'exodo'], ['Levítico', 'Lv', 'levitico'],
            ['Números', 'Nm', 'numeros'], ['Deuteronômio', 'Dt', 'deuteronomio'], ['Josué', 'Js', 'josue'],
            ['Juízes', 'Jz', 'juizes'], ['Rute', 'Rt', 'rute'], ['1 Samuel', '1Sm', '1-samuel'],
            ['2 Samuel', '2Sm', '2-samuel'], ['1 Reis', '1Rs', '1-reis'], ['2 Reis', '2Rs', '2-reis'],
            ['1 Crônicas', '1Cr', '1-cronicas'], ['2 Crônicas', '2Cr', '2-cronicas'], ['Esdras', 'Esd', 'esdras'],
            ['Neemias', 'Ne', 'neemias'], ['Tobias', 'Tb', 'tobias'], ['Judite', 'Jt', 'judite'],
            ['Ester', 'Est', 'ester'], ['Jó', 'Jó', 'jo-livro'], ['Salmos', 'Sl', 'salmos'],
            ['1 Macabeus', '1Mc', '1-macabeus'], ['2 Macabeus', '2Mc', '2-macabeus'], ['Provérbios', 'Pr', 'proverbios'],
            ['Eclesiastes', 'Ecl', 'eclesiastes'], ['Cântico dos Cânticos', 'Ct', 'cantico-dos-canticos'],
            ['Sabedoria', 'Sb', 'sabedoria'], ['Eclesiástico', 'Eclo', 'eclesiastico'], ['Isaías', 'Is', 'isaias'],
            ['Jeremias', 'Jr', 'jeremias'], ['Lamentações', 'Lm', 'lamentacoes'], ['Baruc', 'Br', 'baruc'],
            ['Ezequiel', 'Ez', 'ezequiel'], ['Daniel', 'Dn', 'daniel'], ['Oséias', 'Os', 'oseias'],
            ['Joel', 'Jl', 'joel'], ['Amós', 'Am', 'amos'], ['Abdias', 'Ab', 'abdias'], ['Jonas', 'Jn', 'jonas'],
            ['Miquéias', 'Mq', 'miqueias'], ['Naum', 'Na', 'naum'], ['Habacuc', 'Hab', 'habacuc'],
            ['Sofonias', 'Sf', 'sofonias'], ['Ageu', 'Ag', 'ageu'], ['Zacarias', 'Zc', 'zacarias'],
            ['Malaquias', 'Ml', 'malaquias'],
        ],
        'NT' => [
            ['Mateus', 'Mt', 'mateus'], ['Marcos', 'Mc', 'marcos'], ['Lucas', 'Lc', 'lucas'], ['João', 'Jo', 'joao'],
            ['Atos dos Apóstolos', 'At', 'atos'], ['Romanos', 'Rm', 'romanos'], ['1 Coríntios', '1Cor', '1-corintios'],
            ['2 Coríntios', '2Cor', '2-corintios'], ['Gálatas', 'Gl', 'galatas'], ['Efésios', 'Ef', 'efesios'],
            ['Filipenses', 'Fl', 'filipenses'], ['Colossenses', 'Cl', 'colossenses'],
            ['1 Tessalonicenses', '1Ts', '1-tessalonicenses'], ['2 Tessalonicenses', '2Ts', '2-tessalonicenses'],
            ['1 Timóteo', '1Tm', '1-timoteo'], ['2 Timóteo', '2Tm', '2-timoteo'], ['Tito', 'Tt', 'tito'],
            ['Filêmon', 'Fm', 'filemon'], ['Hebreus', 'Hb', 'hebreus'], ['Tiago', 'Tg', 'tiago'],
            ['1 Pedro', '1Pd', '1-pedro'], ['2 Pedro', '2Pd', '2-pedro'], ['1 João', '1Jo', '1-joao'],
            ['2 João', '2Jo', '2-joao'], ['3 João', '3Jo', '3-joao'], ['Judas', 'Jd', 'judas'],
            ['Apocalipse', 'Ap', 'apocalipse'],
        ],
    ];

    public function run(): void
    {
        $version = BibleVersion::query()->firstOrCreate(
            ['abbreviation' => 'AM'],
            [
                'name' => 'Bíblia Ave-Maria',
                'language' => 'pt_BR',
                'license_notice' => 'Tradução Ave-Maria. Uso sujeito à autorização da Editora Ave-Maria.',
                'is_default' => true,
                'is_active' => true,
            ]
        );

        if ($version->books()->exists()) {
            $this->command?->info('Bíblia já importada — pulando.');

            return;
        }

        $path = database_path('data/biblia-ave-maria.json');
        if (! is_file($path)) {
            throw new RuntimeException("Arquivo da Bíblia não encontrado: {$path}");
        }

        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $sources = ['AT' => $data['antigoTestamento'], 'NT' => $data['novoTestamento']];

        $position = 0;
        $total = 0;

        DB::transaction(function () use ($sources, $version, &$position, &$total): void {
            foreach ($sources as $testament => $books) {
                foreach ($books as $index => $book) {
                    [$name, $abbr, $slug] = self::BOOKS[$testament][$index];
                    $position++;

                    $bookId = DB::table('bible_books')->insertGetId([
                        'bible_version_id' => $version->id,
                        'testament' => $testament,
                        'position' => $position,
                        'name' => $name,
                        'abbreviation' => $abbr,
                        'slug' => $slug,
                        'chapters_count' => count($book['capitulos']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $rows = [];
                    foreach ($book['capitulos'] as $chapter) {
                        foreach ($chapter['versiculos'] as $verse) {
                            $rows[] = [
                                'bible_book_id' => $bookId,
                                'chapter' => (int) $chapter['capitulo'],
                                'verse' => (int) $verse['versiculo'],
                                'text' => trim(str_replace('*', '', $verse['texto'])),
                            ];
                        }
                    }

                    // Remove duplicatas eventuais no JSON de origem (mesmo capítulo/versículo)
                    $rows = collect($rows)->unique(fn ($r) => $r['chapter'].':'.$r['verse'])->values()->all();

                    foreach (array_chunk($rows, 500) as $chunk) {
                        DB::table('bible_verses')->insert($chunk);
                    }
                    $total += count($rows);
                }
            }
        });

        cache()->forget('bible.default_version');
        $this->command?->info("Bíblia importada: {$position} livros, {$total} versículos.");
    }
}
