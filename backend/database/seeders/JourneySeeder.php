<?php

namespace Database\Seeders;

use App\Models\Journey;
use App\Models\Prayer;
use Illuminate\Database\Seeder;

/**
 * Jornadas de exemplo: uma novena (gratuita), um desafio para iniciantes (gratuito)
 * e um plano de leitura (Sancta+). Todo o conteúdo é editável no painel.
 */
class JourneySeeder extends Seeder
{
    public function run(): void
    {
        $ave = Prayer::query()->where('title', 'Ave-Maria')->value('id');
        $consagracao = Prayer::query()->where('title', 'Consagração a Nossa Senhora')->value('id');
        $exame = Prayer::query()->where('title', 'Exame de consciência')->value('id');
        $manha = Prayer::query()->where('title', 'Oração da manhã')->value('id');

        // ─── Novena a Nossa Senhora Aparecida (3 a 11 de outubro) ───
        $novena = Journey::query()->updateOrCreate(['slug' => 'novena-nossa-senhora-aparecida'], [
            'title' => 'Novena a Nossa Senhora Aparecida',
            'type' => 'novena',
            'subtitle' => '9 dias com a Padroeira do Brasil',
            'description' => '<p>Prepare o coração para a festa de 12 de outubro. A cada dia, uma meditação breve, uma passagem da Palavra e a oração da novena.</p>',
            'color' => '#2F5DA8',
            'is_premium' => false,
            'is_featured' => true,
            'status' => 'published',
            'sort_order' => 1,
        ]);

        $themes = [
            ['Maria, mulher da escuta', 'Lc 1,26-38', 'No silêncio de Nazaré, Maria ouviu o anjo e disse "sim". Peça hoje a graça de escutar a voz de Deus no meio do barulho do dia.'],
            ['Maria, mulher que serve', 'Lc 1,39-45', 'Grávida, Maria partiu apressadamente para servir Isabel. Em quem Deus te pede para servir hoje?'],
            ['Maria, mulher que louva', 'Lc 1,46-55', 'O Magnificat brota de um coração agradecido. Faça hoje uma lista de dez graças recebidas.'],
            ['Maria, mãe na pobreza', 'Lc 2,1-7', 'Jesus nasceu sem lugar na hospedaria. Maria acolhe a simplicidade. Onde Deus está escondido na sua rotina?'],
            ['Maria, mulher que guarda', 'Lc 2,15-19', '"Maria guardava todas essas coisas, meditando-as no coração." Reserve cinco minutos de silêncio hoje.'],
            ['Maria em Caná', 'Jo 2,1-11', '"Fazei tudo o que Ele vos disser." Entregue a Maria a "falta de vinho" da sua família.'],
            ['Maria aos pés da cruz', 'Jo 19,25-27', 'Na dor, Maria permaneceu. Leve a ela as dores que você carrega e as de quem você ama.'],
            ['Maria no Cenáculo', 'At 1,12-14', 'Com os apóstolos, Maria perseverou em oração. Reze hoje pela Igreja e pelo Brasil.'],
            ['A imagem encontrada nas águas', 'Jo 2,1-5', 'Em 1717, pescadores encontraram nas águas do Paraíba a pequena imagem. Deus age nos humildes. Consagre-se hoje à Mãe Aparecida.'],
        ];

        foreach ($themes as $i => [$title, $reference, $meditation]) {
            $novena->steps()->updateOrCreate(['position' => $i + 1], [
                'title' => 'Dia '.($i + 1).' — '.$title,
                'bible_reference' => $reference,
                'content' => '<p>'.e($meditation).'</p><p><strong>Oração da novena:</strong> Ó incomparável Senhora da Conceição Aparecida, Mãe de Deus e nossa Mãe, olhai por nós, por nossas famílias e por todo o Brasil. Alcançai-nos de Jesus as graças de que necessitamos. Amém.</p>',
                'prayer_id' => $i === 8 ? $consagracao : $ave,
                'duration_seconds' => 600,
            ]);
        }

        // ─── 7 dias para começar a rezar (gratuito) ───
        $start = Journey::query()->updateOrCreate(['slug' => '7-dias-para-comecar-a-rezar'], [
            'title' => '7 dias para começar a rezar',
            'type' => 'challenge',
            'subtitle' => 'Crie o hábito com 10 minutos por dia',
            'description' => '<p>Um caminho simples para quem quer (re)começar a vida de oração. Cada dia traz um passo prático.</p>',
            'color' => '#E3B65B',
            'is_premium' => false,
            'is_featured' => true,
            'status' => 'published',
            'sort_order' => 2,
        ]);

        $days = [
            ['Um lugar e um horário', 'Mt 6,5-6', 'Escolha um lugar tranquilo e um horário fixo. Jesus nos convida a entrar no quarto e fechar a porta.', $manha],
            ['Falar com Deus como amigo', 'Jo 15,12-15', 'Converse com Deus com suas palavras. Ele te chama de amigo.', null],
            ['Escutar a Palavra', 'Sl 118,105', 'Leia devagar um trecho bíblico. Qual palavra tocou seu coração?', null],
            ['Agradecer', '1Ts 5,16-18', 'Termine o dia listando três motivos de gratidão.', null],
            ['Pedir perdão', 'Lc 15,11-24', 'O Pai corre ao encontro do filho que volta. Faça um exame de consciência.', $exame],
            ['Interceder', 'Tg 5,16', 'Reze por três pessoas pelo nome. A oração do justo tem grande poder.', null],
            ['Permanecer', 'Jo 15,4-5', 'Permaneça em silêncio por cinco minutos, apenas na presença de Deus.', null],
        ];

        foreach ($days as $i => [$title, $reference, $text, $prayer]) {
            $start->steps()->updateOrCreate(['position' => $i + 1], [
                'title' => 'Dia '.($i + 1).' — '.$title,
                'bible_reference' => $reference,
                'content' => '<p>'.e($text).'</p>',
                'prayer_id' => $prayer,
                'duration_seconds' => 600,
            ]);
        }

        // ─── Evangelho de João em 21 dias (Sancta+) ───
        $john = Journey::query()->updateOrCreate(['slug' => 'evangelho-de-joao-em-21-dias'], [
            'title' => 'O Evangelho de João em 21 dias',
            'type' => 'reading_plan',
            'subtitle' => 'Um capítulo por dia com reflexão',
            'description' => '<p>Leia o Evangelho do discípulo amado, um capítulo por dia, com perguntas para meditação.</p>',
            'color' => '#6B3FA0',
            'is_premium' => true,
            'is_featured' => false,
            'status' => 'published',
            'sort_order' => 3,
        ]);

        for ($chapter = 1; $chapter <= 21; $chapter++) {
            $john->steps()->updateOrCreate(['position' => $chapter], [
                'title' => "Dia {$chapter} — João, capítulo {$chapter}",
                'bible_reference' => "Jo {$chapter}",
                'content' => '<p>Leia o capítulo com calma. Pergunte-se: o que este texto revela sobre Jesus? O que ele pede de mim hoje?</p>',
                'duration_seconds' => 900,
            ]);
        }
    }
}
