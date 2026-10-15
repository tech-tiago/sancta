<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Prayer;
use Illuminate\Database\Seeder;

/**
 * Categorias e orações: orações tradicionais (domínio público), as 14 Orações
 * Eucarísticas e a Via-Sacra herdadas do app antigo (em blocos litúrgicos) e
 * alguns exemplos Sancta+ para demonstrar o conteúdo exclusivo de assinantes.
 */
class PrayerSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'essenciais' => ['Orações essenciais', 'As orações que todo católico reza', 'sparkles', '#E3B65B', 1],
            'manha-e-noite' => ['Manhã e noite', 'Comece e termine o dia com Deus', 'sun', '#E8B04B', 2],
            'marianas' => ['Marianas', 'Com Maria, Mãe de Jesus', 'heart', '#4F7CC4', 3],
            'terco' => ['Terço e Rosário', 'Contemple os mistérios de Cristo', 'circle-stack', '#7C5CBF', 4],
            'santa-missa' => ['Santa Missa', 'Orações Eucarísticas e partes da Missa', 'sun', '#B8862F', 5],
            'via-sacra' => ['Via-Sacra', 'Caminhe com Jesus até a cruz', 'plus', '#8E3B46', 6],
            'espirito-santo' => ['Espírito Santo', 'Vinde, Espírito Santo', 'fire', '#D65A31', 7],
            'familia' => ['Família', 'Orações pela sua casa', 'home', '#3E8E7E', 8],
            'para-dormir' => ['Para dormir', 'Descanse em paz no Senhor', 'moon', '#2B3A67', 9],
        ];

        $cat = [];
        foreach ($categories as $slug => [$name, $description, $icon, $color, $order]) {
            $cat[$slug] = Category::query()->updateOrCreate(['slug' => $slug], [
                'name' => $name, 'description' => $description, 'icon' => $icon, 'color' => $color,
                'sort_order' => $order, 'is_active' => true,
            ]);
        }

        // ─── Orações tradicionais ───
        foreach ($this->traditional() as $i => [$category, $title, $subtitle, $body, $premium, $minutes]) {
            Prayer::query()->updateOrCreate(['title' => $title], [
                'category_id' => $cat[$category]->id,
                'subtitle' => $subtitle,
                'body' => $body,
                'duration_seconds' => $minutes * 60,
                'author' => $premium ? 'Equipe Sancta' : 'Tradição da Igreja',
                'is_premium' => $premium,
                'is_featured' => in_array($title, ['Pai-Nosso', 'Oração da manhã', 'Exame de consciência'], true),
                'status' => 'published',
                'sort_order' => $i,
            ]);
        }

        // ─── Orações Eucarísticas e Via-Sacra (blocos litúrgicos do app antigo) ───
        $files = glob(database_path('data/oracoes/*.json')) ?: [];
        sort($files, SORT_NATURAL);

        foreach ($files as $i => $file) {
            $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $isViaSacra = str_contains($file, 'viasacra');

            Prayer::query()->updateOrCreate(['title' => $data['titulo']], [
                'category_id' => $cat[$isViaSacra ? 'via-sacra' : 'santa-missa']->id,
                'subtitle' => $isViaSacra ? '14 estações meditadas' : 'Missal Romano',
                'blocks' => $this->toBuilderBlocks($data['blocos'] ?? []),
                'duration_seconds' => $isViaSacra ? 40 * 60 : 8 * 60,
                'author' => 'Missal Romano',
                'is_premium' => false,
                'status' => 'published',
                'sort_order' => 100 + $i,
            ]);
        }
    }

    /** Converte o formato do app antigo para o formato do Builder do painel. */
    private function toBuilderBlocks(array $blocks): array
    {
        return array_values(array_map(function (array $block): array {
            $type = $block['tipo'] ?? 'paragrafo';
            $data = $block;
            unset($data['tipo']);

            if ($type === 'expansivel') {
                $data['blocos'] = $this->toBuilderBlocks($block['blocos'] ?? []);
            }

            return ['type' => $type, 'data' => $data];
        }, $blocks));
    }

    private function p(string ...$paragraphs): string
    {
        return implode('', array_map(fn ($p) => '<p>'.e($p).'</p>', $paragraphs));
    }

    /** @return list<array{0:string,1:string,2:string,3:string,4:bool,5:int}> */
    private function traditional(): array
    {
        return [
            ['essenciais', 'Sinal da Cruz', 'Início de toda oração', $this->p('Pelo sinal da Santa Cruz, livrai-nos, Deus, nosso Senhor, dos nossos inimigos.', 'Em nome do Pai, e do Filho, e do Espírito Santo. Amém.'), false, 1],
            ['essenciais', 'Pai-Nosso', 'A oração que Jesus nos ensinou', $this->p('Pai nosso que estais nos céus, santificado seja o vosso nome; venha a nós o vosso Reino; seja feita a vossa vontade, assim na terra como no céu.', 'O pão nosso de cada dia nos dai hoje; perdoai-nos as nossas ofensas, assim como nós perdoamos a quem nos tem ofendido; e não nos deixeis cair em tentação, mas livrai-nos do mal. Amém.'), false, 1],
            ['essenciais', 'Ave-Maria', 'Saudação do Anjo a Maria', $this->p('Ave, Maria, cheia de graça, o Senhor é convosco; bendita sois vós entre as mulheres, e bendito é o fruto do vosso ventre, Jesus.', 'Santa Maria, Mãe de Deus, rogai por nós, pecadores, agora e na hora de nossa morte. Amém.'), false, 1],
            ['essenciais', 'Glória ao Pai', 'Doxologia', $this->p('Glória ao Pai, e ao Filho, e ao Espírito Santo. Como era no princípio, agora e sempre. Amém.'), false, 1],
            ['essenciais', 'Creio (Símbolo dos Apóstolos)', 'Profissão de fé', $this->p('Creio em Deus Pai todo-poderoso, criador do céu e da terra. E em Jesus Cristo, seu único Filho, nosso Senhor, que foi concebido pelo poder do Espírito Santo; nasceu da Virgem Maria; padeceu sob Pôncio Pilatos, foi crucificado, morto e sepultado. Desceu à mansão dos mortos; ressuscitou ao terceiro dia; subiu aos céus, está sentado à direita de Deus Pai todo-poderoso, donde há de vir a julgar os vivos e os mortos.', 'Creio no Espírito Santo, na santa Igreja Católica, na comunhão dos santos, na remissão dos pecados, na ressurreição da carne, na vida eterna. Amém.'), false, 2],
            ['essenciais', 'Ato de contrição', 'Para pedir perdão', $this->p('Meu Deus, eu me arrependo de todo o coração de vos ter ofendido, porque sois infinitamente bom e amável. Prometo, com a vossa graça, emendar-me e evitar as ocasiões de pecado. Meu Jesus, misericórdia!'), false, 1],
            ['essenciais', 'Santo Anjo', 'Ao anjo da guarda', $this->p('Santo Anjo do Senhor, meu zeloso guardador, se a ti me confiou a piedade divina, sempre me rege, me guarda, me governa e me ilumina. Amém.'), false, 1],
            ['essenciais', 'Oração a São Miguel Arcanjo', 'Proteção no combate espiritual', $this->p('São Miguel Arcanjo, defendei-nos no combate; sede o nosso refúgio contra as maldades e ciladas do demônio. Ordene-lhe Deus, instantemente o pedimos; e vós, príncipe da milícia celeste, pela virtude divina, precipitai no inferno a Satanás e aos outros espíritos malignos, que andam pelo mundo para perder as almas. Amém.'), false, 1],
            ['marianas', 'Salve-Rainha', 'Antífona mariana', $this->p('Salve, Rainha, Mãe de misericórdia, vida, doçura e esperança nossa, salve! A vós bradamos, os degredados filhos de Eva. A vós suspiramos, gemendo e chorando neste vale de lágrimas.', 'Eia, pois, advogada nossa, esses vossos olhos misericordiosos a nós volvei. E, depois deste desterro, mostrai-nos Jesus, bendito fruto do vosso ventre. Ó clemente, ó piedosa, ó doce sempre Virgem Maria.', 'Rogai por nós, santa Mãe de Deus, para que sejamos dignos das promessas de Cristo. Amém.'), false, 2],
            ['marianas', 'Angelus', 'Ao meio-dia, com Maria', $this->p('O Anjo do Senhor anunciou a Maria. — E ela concebeu do Espírito Santo. Ave, Maria…', 'Eis aqui a serva do Senhor. — Faça-se em mim segundo a vossa palavra. Ave, Maria…', 'E o Verbo se fez carne. — E habitou entre nós. Ave, Maria…', 'Rogai por nós, santa Mãe de Deus. — Para que sejamos dignos das promessas de Cristo.', 'Oremos: Infundi, Senhor, como vos pedimos, a vossa graça em nossas almas, para que nós, que pela anunciação do Anjo conhecemos a encarnação de Cristo, vosso Filho, por sua paixão e morte na cruz sejamos conduzidos à glória da ressurreição. Pelo mesmo Cristo, Senhor nosso. Amém.'), false, 3],
            ['marianas', 'Consagração a Nossa Senhora', 'Ó minha Senhora e minha Mãe', $this->p('Ó minha Senhora e também minha Mãe, eu me ofereço inteiramente todo(a) a vós; e, em prova da minha devoção para convosco, eu vos consagro neste dia os meus olhos, os meus ouvidos, a minha boca, o meu coração e inteiramente todo o meu ser.', 'E porque assim sou vosso(a), ó incomparável Mãe, guardai-me e defendei-me como coisa e propriedade vossa. Amém.'), false, 1],
            ['espirito-santo', 'Vinde, Espírito Santo', 'Invocação ao Espírito Santo', $this->p('Vinde, Espírito Santo, enchei os corações dos vossos fiéis e acendei neles o fogo do vosso amor.', 'Enviai o vosso Espírito e tudo será criado. — E renovareis a face da terra.', 'Oremos: Ó Deus, que instruístes os corações dos vossos fiéis com a luz do Espírito Santo, concedei-nos que no mesmo Espírito saibamos o que é reto e gozemos sempre de sua consolação. Por Cristo, Senhor nosso. Amém.'), false, 2],
            ['terco', 'Como rezar o Terço', 'Passo a passo e mistérios do dia', $this->p('1. Faça o Sinal da Cruz e reze o Creio.', '2. Reze um Pai-Nosso, três Ave-Marias (pela fé, esperança e caridade) e um Glória.', '3. Para cada um dos cinco mistérios: anuncie o mistério, reze um Pai-Nosso, dez Ave-Marias e um Glória. Pode-se acrescentar a jaculatória: "Ó meu Jesus, perdoai-nos, livrai-nos do fogo do inferno, levai as almas todas para o céu e socorrei principalmente as que mais precisarem."', '4. Ao final, reze a Salve-Rainha.', 'Mistérios Gozosos (segunda e sábado): Anunciação; Visitação; Nascimento de Jesus; Apresentação no Templo; Perda e encontro de Jesus no Templo.', 'Mistérios Dolorosos (terça e sexta): Agonia no Horto; Flagelação; Coroação de espinhos; Jesus carrega a cruz; Crucifixão e morte.', 'Mistérios Gloriosos (quarta e domingo): Ressurreição; Ascensão; Vinda do Espírito Santo; Assunção de Maria; Coroação de Maria.', 'Mistérios Luminosos (quinta-feira): Batismo no Jordão; Bodas de Caná; Anúncio do Reino; Transfiguração; Instituição da Eucaristia.'), false, 20],
            ['manha-e-noite', 'Oração da manhã', 'Ofereça o seu dia a Deus', $this->p('Senhor, no silêncio deste dia que amanhece, venho pedir paz, sabedoria e força. Quero olhar o mundo com olhos cheios de amor, ser paciente, compreensivo, manso e prudente.', 'Ofereço-vos este dia: meus trabalhos, alegrias e sofrimentos. Que tudo o que eu fizer seja para a vossa glória e para o bem dos meus irmãos. Maria, minha Mãe, guiai os meus passos. Amém.'), false, 2],
            ['manha-e-noite', 'Oração da noite', 'Entregue o seu descanso', $this->p('Senhor, ao fim deste dia, agradeço por tudo o que recebi das vossas mãos. Perdoai as minhas faltas, as palavras que feriram e o bem que deixei de fazer.', 'Guardai o meu sono e o de todos os que amo. Que os vossos anjos velem por esta casa. Em vossas mãos, Senhor, entrego o meu espírito. Amém.'), false, 2],
            ['manha-e-noite', 'Exame de consciência', 'Revisão orante do dia (guiada)', $this->p('1. Presença: coloque-se diante de Deus. Respire fundo e peça a luz do Espírito Santo.', '2. Gratidão: recorde três momentos de hoje pelos quais você é grato.', '3. Revisão: percorra o dia, hora a hora. Onde você percebeu Deus? Onde se afastou do amor?', '4. Perdão: peça perdão com confiança, sabendo que a misericórdia de Deus é maior que qualquer falta.', '5. Propósito: escolha um pequeno passo concreto para amanhã. Termine com um Pai-Nosso.'), true, 10],
            ['familia', 'Oração pela família', 'Abençoe a sua casa', $this->p('Senhor Jesus, que vivestes em Nazaré com Maria e José, abençoai a nossa família. Fazei de nossa casa um lugar de paz, de perdão e de alegria.', 'Protegei os nossos filhos, curai as nossas feridas, uni os que estão distantes e ensinai-nos a amar como vós amais. Sagrada Família de Nazaré, rogai por nós. Amém.'), true, 3],
            ['para-dormir', 'Oração para dormir em paz', 'Meditação noturna para acalmar o coração', $this->p('Feche os olhos. Respire devagar. A cada inspiração, diga em seu coração: "Jesus". A cada expiração: "eu confio em vós".', 'Entregue a Ele as preocupações de hoje, uma a uma. Imagine-as colocadas aos pés da cruz.', '"Em paz me deito e logo adormeço, pois só vós, Senhor, me fazeis repousar em segurança." Fique em silêncio, descansando no amor de Deus.'), true, 8],
        ];
    }
}
