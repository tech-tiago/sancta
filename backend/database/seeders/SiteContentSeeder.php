<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * Páginas institucionais (termos, privacidade/LGPD, exclusão de conta, sobre), FAQ e
 * depoimentos de exemplo (inativos). Revise os textos jurídicos com um advogado.
 */
class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'termos-de-uso' => ['Termos de Uso', $this->terms()],
            'politica-de-privacidade' => ['Política de Privacidade', $this->privacy()],
            'excluir-conta' => ['Exclusão de conta e dados', $this->deletion()],
            'sobre' => ['Sobre o Sancta', '<p>O <strong>Sancta</strong> nasceu para ajudar católicos a rezar todos os dias: Bíblia, liturgia diária, orações guiadas, jornadas e novenas, igrejas próximas com horários de missa e muito mais — em um só lugar, com beleza e simplicidade.</p><p>Ora · Lê · Vive.</p>'],
        ];

        foreach ($pages as $slug => [$title, $content]) {
            Page::query()->firstOrCreate(['slug' => $slug], ['title' => $title, 'content' => $content, 'is_published' => true]);
        }

        $faqs = [
            ['O Sancta é gratuito?', 'Sim. Bíblia, liturgia diária, orações essenciais, mapa de igrejas e notícias são gratuitos. O Sancta+ libera orações guiadas, jornadas completas, reflexões diárias, explicações de versículos com IA e eventos exclusivos.'],
            ['Como assino o Sancta+?', 'No aplicativo, toque em "Sancta+" e escolha um plano (cobrança pelo Google Play). No site, você também pode assinar com Pix ou cartão de crédito em até 12x e usar a mesma conta no app.'],
            ['Posso cancelar quando quiser?', 'Sim. Assinaturas feitas pelo Google Play são canceladas em Play Store → Pagamentos e assinaturas. Planos pagos no site não renovam automaticamente: você usa até o fim do período contratado.'],
            ['Existe período de teste?', 'O plano anual inclui 7 dias grátis no aplicativo. Se cancelar antes do fim do teste, nada é cobrado.'],
            ['Como cadastro a minha igreja?', 'No app (Igrejas → Sugerir igreja) ou no site (Igrejas → Cadastrar). Nossa equipe confere as informações e publica no mapa.'],
            ['Encontrei um horário de missa errado. O que faço?', 'Abra a igreja no mapa e toque em "Corrigir informação". A correção é revisada pela equipe antes de ser publicada.'],
            ['Meus dados estão seguros?', 'Sim. Usamos conexão criptografada (HTTPS), senhas protegidas com hash, pagamentos processados por provedores certificados (Google Play e Mercado Pago) — não armazenamos dados de cartão.'],
            ['Como excluo minha conta?', 'No app: Perfil → Configurações → Excluir conta. Ou pelo site em "Excluir conta". A exclusão remove seus dados pessoais em até 30 dias.'],
        ];

        foreach ($faqs as $i => [$question, $answer]) {
            Faq::query()->firstOrCreate(['question' => $question], ['answer' => $answer, 'audience' => 'both', 'sort_order' => $i, 'is_active' => true]);
        }

        // Exemplos INATIVOS: substitua por depoimentos reais antes de ativar.
        $testimonials = [
            ['Exemplo — Maria S.', 'Salvador/BA', 'Substitua por um depoimento real de um usuário do Sancta.'],
            ['Exemplo — João P.', 'Porto Seguro/BA', 'Substitua por um depoimento real de um usuário do Sancta.'],
            ['Exemplo — Ana L.', 'São Paulo/SP', 'Substitua por um depoimento real de um usuário do Sancta.'],
        ];

        foreach ($testimonials as $i => [$author, $location, $content]) {
            Testimonial::query()->firstOrCreate(['author' => $author], ['location' => $location, 'content' => $content, 'rating' => 5, 'is_active' => false, 'sort_order' => $i]);
        }
    }

    private function terms(): string
    {
        return <<<'HTML'
<p><em>Modelo inicial — revise com sua assessoria jurídica antes da publicação.</em></p>
<h2>1. Aceitação</h2>
<p>Ao criar uma conta ou usar o aplicativo e o site Sancta, você concorda com estes Termos e com a Política de Privacidade.</p>
<h2>2. Conta</h2>
<p>Você é responsável por manter a confidencialidade das suas credenciais. É vedado criar contas falsas, automatizadas ou usar o serviço para fins ilícitos.</p>
<h2>3. Assinatura Sancta+</h2>
<ul>
<li>Assinaturas contratadas no aplicativo são cobradas e renovadas pelo Google Play, conforme as regras da loja, até o cancelamento.</li>
<li>Planos contratados no site (Pix ou cartão) são pré-pagos, sem renovação automática, e dão acesso pelo período contratado.</li>
<li>O período de teste, quando oferecido, é convertido em assinatura paga ao final, salvo cancelamento prévio.</li>
<li>Reembolsos seguem o Código de Defesa do Consumidor (direito de arrependimento de 7 dias para compras online) e as políticas do provedor de pagamento.</li>
</ul>
<h2>4. Conteúdo enviado por usuários</h2>
<p>Sugestões de igrejas e intenções de oração passam por moderação e podem ser recusadas ou editadas. Não publique dados pessoais de terceiros.</p>
<h2>5. Propriedade intelectual</h2>
<p>Textos, áudios, marcas e o design do Sancta são protegidos. Textos bíblicos e litúrgicos pertencem aos respectivos detentores de direitos.</p>
<h2>6. Limitação de responsabilidade</h2>
<p>Horários de missas e informações de igrejas são colaborativos e podem mudar; confirme com a paróquia.</p>
<h2>7. Contato</h2>
<p>Dúvidas: contato@sancta.app.br</p>
HTML;
    }

    private function privacy(): string
    {
        return <<<'HTML'
<p><em>Modelo inicial em conformidade com a LGPD (Lei 13.709/2018) — revise com sua assessoria jurídica.</em></p>
<h2>1. Quem somos</h2>
<p>O Sancta é o controlador dos dados pessoais tratados no aplicativo e no site.</p>
<h2>2. Dados que coletamos</h2>
<ul>
<li><strong>Cadastro:</strong> nome, e-mail, foto (opcional) e, se usar o login do Google, o identificador da conta Google.</li>
<li><strong>Uso:</strong> anotações e destaques bíblicos, favoritos, progresso em jornadas, tempo de oração.</li>
<li><strong>Localização:</strong> apenas quando você permite, para mostrar igrejas próximas. Não armazenamos seu histórico de localização.</li>
<li><strong>Pagamentos:</strong> registros de transações (valor, status, plano). Dados de cartão são tratados exclusivamente pelo Google Play ou Mercado Pago.</li>
<li><strong>Dispositivo:</strong> token de notificações push e versão do aplicativo.</li>
</ul>
<h2>3. Finalidades e bases legais</h2>
<p>Execução de contrato (prestar o serviço e a assinatura), legítimo interesse (segurança, prevenção a fraudes e melhorias) e consentimento (notificações e comunicações de marketing, que você pode revogar a qualquer momento).</p>
<h2>4. Compartilhamento</h2>
<p>Com provedores necessários à operação: hospedagem, Google (login, Play Billing e notificações), Mercado Pago (pagamentos) e serviço de IA (apenas o texto do versículo, sem dados pessoais).</p>
<h2>5. Seus direitos</h2>
<p>Você pode solicitar acesso, correção, portabilidade e exclusão dos seus dados, ou revogar consentimentos, pelo app ou pelo e-mail contato@sancta.app.br.</p>
<h2>6. Retenção e segurança</h2>
<p>Mantemos os dados enquanto a conta estiver ativa. Registros de pagamento podem ser mantidos pelo prazo legal. Usamos criptografia em trânsito, senhas com hash e controle de acesso.</p>
<h2>7. Crianças</h2>
<p>O serviço não é direcionado a menores de 13 anos sem supervisão dos responsáveis.</p>
HTML;
    }

    private function deletion(): string
    {
        return <<<'HTML'
<p>Você pode excluir sua conta do Sancta e os dados associados a qualquer momento:</p>
<ol>
<li><strong>Pelo aplicativo:</strong> Perfil → Configurações → Excluir conta.</li>
<li><strong>Pelo site:</strong> entre em "Minha conta" e clique em "Excluir minha conta".</li>
<li><strong>Por e-mail:</strong> escreva para contato@sancta.app.br a partir do e-mail cadastrado.</li>
</ol>
<p><strong>O que é excluído:</strong> nome, e-mail, foto, anotações, favoritos, progresso e dispositivos. <strong>O que pode ser mantido:</strong> registros de pagamentos pelo prazo exigido em lei (anonimizados quando possível).</p>
<p>Atenção: excluir a conta não cancela automaticamente uma assinatura do Google Play — cancele também em Play Store → Pagamentos e assinaturas.</p>
HTML;
    }
}
