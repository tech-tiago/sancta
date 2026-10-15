<?php

namespace Database\Seeders;

use App\Models\Saint;
use Illuminate\Database\Seeder;

/** Santos do mês de outubro (exemplo). Complete o calendário pelo painel. */
class SaintSeeder extends Seeder
{
    public function run(): void
    {
        $saints = [
            [10, 1, 'Santa Teresinha do Menino Jesus', 'Virgem e Doutora da Igreja', 'Carmelita francesa que ensinou a "pequena via": fazer as pequenas coisas com grande amor. Padroeira das missões.'],
            [10, 2, 'Santos Anjos da Guarda', 'Memória', 'A Igreja celebra os anjos que Deus confia a cada pessoa para guardá-la e conduzi-la no caminho do bem.'],
            [10, 3, 'Santos André de Soveral, Ambrósio Ferro e companheiros', 'Protomártires do Brasil', 'Mártires de Cunhaú e Uruaçu (RN), em 1645, deram a vida pela fé católica. Canonizados em 2017.'],
            [10, 4, 'São Francisco de Assis', 'Religioso', 'Deixou tudo para seguir Cristo pobre e crucificado. Amou a criação como obra do Pai e fundou a Ordem dos Frades Menores.'],
            [10, 5, 'Santa Faustina Kowalska', 'Virgem', 'Religiosa polonesa a quem Jesus confiou a mensagem da Divina Misericórdia: "Jesus, eu confio em vós".'],
            [10, 7, 'Nossa Senhora do Rosário', 'Memória', 'Celebração que convida a contemplar os mistérios de Cristo com Maria, pela oração do Rosário.'],
            [10, 11, 'São João XXIII', 'Papa', 'O "Papa bom", que convocou o Concílio Vaticano II para abrir as janelas da Igreja ao mundo.'],
            [10, 12, 'Nossa Senhora da Conceição Aparecida', 'Padroeira do Brasil — Solenidade', 'Em 1717, pescadores encontraram nas águas do rio Paraíba a pequena imagem da Imaculada Conceição. Desde então, o povo brasileiro recorre à Mãe Aparecida.'],
            [10, 15, 'Santa Teresa de Ávila', 'Virgem e Doutora da Igreja', 'Reformadora do Carmelo e mestra de oração: "Nada te perturbe, nada te espante; quem a Deus tem, nada lhe falta. Só Deus basta."'],
            [10, 16, 'Santa Margarida Maria Alacoque', 'Virgem', 'Religiosa da Visitação que difundiu a devoção ao Sagrado Coração de Jesus.'],
            [10, 17, 'Santo Inácio de Antioquia', 'Bispo e mártir', 'Discípulo dos apóstolos, escreveu cartas ardentes a caminho do martírio em Roma: "Sou trigo de Deus".'],
            [10, 18, 'São Lucas', 'Evangelista', 'Médico e companheiro de São Paulo, autor do Evangelho da misericórdia e dos Atos dos Apóstolos.'],
            [10, 22, 'São João Paulo II', 'Papa', '"Não tenhais medo! Abri as portas a Cristo." Pontífice peregrino, amigo dos jovens e das famílias.'],
            [10, 25, 'Santo Antônio de Sant\'Ana Galvão', 'Presbítero', 'Frei Galvão, primeiro santo nascido no Brasil, conhecido pela caridade e pelas "pílulas" de oração.'],
            [10, 28, 'São Simão e São Judas Tadeu', 'Apóstolos', 'Apóstolos de Jesus. São Judas Tadeu é invocado como padroeiro das causas difíceis.'],
        ];

        foreach ($saints as [$month, $day, $name, $title, $bio]) {
            Saint::query()->updateOrCreate(['name' => $name], [
                'feast_month' => $month,
                'feast_day' => $day,
                'title' => $title,
                'biography' => $bio,
                'is_active' => true,
            ]);
        }
    }
}
