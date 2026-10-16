# Sancta — Sobre o projeto

> **Ora · Lê · Vive.** Sua jornada de fé, todos os dias.

## 1. Visão

O Sancta é o app católico brasileiro para **criar o hábito diário da oração** — com a beleza e a simplicidade do Hallow, mas pensado para a realidade do Brasil: liturgia da CNBB, Bíblia em português, novenas tradicionais, **igrejas e horários de missa perto de você**, Pix e parcelamento.

**Público:** católicos praticantes e pessoas retomando a fé (25–60 anos), famílias, catequistas, grupos de oração, paróquias.

**Proposta de valor**
1. Tudo da vida de oração em um lugar: Bíblia, liturgia, orações, jornadas, igrejas.
2. Hábito: sequência de dias, lembretes, "continuar de onde parou".
3. Comunidade: intenções de oração, igrejas colaborativas, eventos.
4. Beleza e leveza: design premium, temas claro/escuro/pergaminho, funciona offline.

## 2. Referência: o que o Hallow faz e o que adotamos

| No Hallow | No Sancta | Comentário |
|---|---|---|
| Orações guiadas em áudio, meditações | ✅ Orações em texto **e áudio**, editáveis no painel | Gravar áudios é o principal diferencial de conteúdo — prioridade nº 1 após o lançamento |
| Desafios (Quaresma, Advento, "Pray25") | ✅ **Jornadas**: novenas, planos de leitura, desafios com progresso diário | Já sai com a Novena de Aparecida (03 a 11/10) |
| Daily (Evangelho do dia, reflexão) | ✅ Liturgia diária + reflexão Sancta+ + versículo do dia | Liturgia importada automaticamente e editável |
| Bíblia | ✅ Completa, com destaques, notas, marcadores, voz e **IA** | IA explica versículos "à luz do Catecismo" |
| Terço, Via-Sacra, Orações da Missa | ✅ Terço guiado, Via-Sacra, 14 Orações Eucarísticas com posturas | Herdado do app antigo e melhorado |
| Sequência de dias e estatísticas | ✅ Streak, recorde, minutos de oração, semana | Gamificação leve, sem "competição" |
| Comunidade / intenções | ✅ Intenções moderadas, "Rezei por esta intenção" | Moderação evita abuso |
| Assinatura com teste grátis | ✅ Sancta+ (mensal, semestral, anual com 7 dias grátis) | Igual ao modelo do Hallow |
| — | ➕ **Igrejas e horários de missa no mapa** | Diferencial forte no Brasil (o Hallow não tem) |
| — | ➕ **Pix e cartão em 12x pelo site** | Adequado ao mercado brasileiro |
| — | ➕ Notícias da Igreja e transmissões ao vivo | Mantidos do app antigo, agora gerenciáveis |
| Conteúdo de celebridades/pregadores | ⏳ Roadmap | Parcerias com padres, músicos e comunidades |

**O que deixamos de fora (de propósito):** loja, doações avulsas (substituídas pela assinatura), feed social aberto (exige moderação pesada), anúncios.

## 3. Funcionalidades

### Gratuito (para atrair e reter)
Bíblia completa e busca · liturgia diária · versículo do dia · orações essenciais e da Missa · Via-Sacra · Terço · jornadas gratuitas (ex.: Novena de Aparecida) · mapa de igrejas e horários · notícias · ao vivo · intenções · sequência de dias · temas.

### Sancta+ (para converter)
Orações guiadas premium (texto e áudio) · todas as jornadas e planos de leitura · reflexão diária do Evangelho/homilia · explicação de versículos por IA · eventos/retiros exclusivos · apoio à evangelização.

> Regra de ouro: o gratuito precisa ser **excelente** (é o que gera avaliações e indicações); o Sancta+ aprofunda.

## 4. Modelo de negócio

| Plano | Preço sugerido | Equivalente/mês | Observação |
|---|---|---|---|
| Mensal | R$ 19,90 | R$ 19,90 | Porta de entrada |
| Semestral | R$ 89,90 | R$ 14,98 | "Economize 25%" |
| **Anual** | **R$ 149,90** | **R$ 12,49** | "Mais popular" · 7 dias grátis no app · 12x no cartão pelo site |

- Preços, selos, benefícios e teste grátis são **editáveis no painel** (no Google Play, também no Play Console).
- **Cupons** para campanhas (paróquias, retiros, influenciadores).
- **Cortesias** para benfeitores do app antigo, parceiros e equipe.
- **Canais de venda:** app (Google Play Billing, renovação automática) e site (Pix/cartão, pré-pago). A política do Google Play impede direcionar o usuário do app para pagamento externo de conteúdo digital; o site é divulgado por fora (redes sociais, paróquias, QR Codes).
- **Taxas:** Google Play cobra 15% em assinaturas; Mercado Pago cerca de 1% no Pix e 4–5% no cartão (consulte as taxas vigentes) — incentivar o plano anual pelo site melhora a margem.

**Métricas para acompanhar (o painel já mostra várias):** novos usuários, ativação (1ª oração concluída), retenção D1/D7/D30, usuários rezando hoje, sequência média, conversão para Sancta+, MRR, churn, receita por canal, CTR dos avisos.

## 5. Arquitetura

```
 App Flutter (Android/iOS)         Site (navegador)            Painel (equipe)
        │  HTTPS + token                │  HTTPS + sessão            │  HTTPS + sessão + 2FA + IP
        ▼                               ▼                            ▼
 ┌──────────────────────────── Laravel 12 (PHP 8.2+) ─────────────────────────────┐
 │ API v1 (Sanctum) │ Site (Blade) │ Filament (painel) │ Agendador │ Webhooks     │
 │ Serviços: assinaturas · pagamentos · liturgia · RSS · IA · push · moderação    │
 └───────────────┬───────────────────────────────────────────────┬────────────────┘
                 ▼                                               ▼
           MySQL 8 (InnoDB)                    Mercado Pago · Google Play · Firebase (push)
                                               Google (login) · Gemini (IA) · API de liturgia
```

- **Fonte única da verdade:** o servidor. O app nunca decide sozinho se o usuário é assinante.
- **Tudo gerenciável:** conteúdo, preços, recursos (liga/desliga), versão mínima do app, manutenção, avisos, push, mapas e textos.
- **Leveza:** a tela inicial chega em uma única requisição; Bíblia e orações ficam em cache no aparelho; nada de WebView para conteúdo.

### Papéis da equipe
| Papel | Pode |
|---|---|
| Moderador | Aprovar/recusar sugestões de igrejas e intenções |
| Editor | Tudo de conteúdo: orações, jornadas, liturgia, igrejas, notícias, avisos, páginas |
| Administrador | + usuários, assinaturas, pagamentos (leitura), planos, cupons, push, configurações |
| Super administrador | + criar equipe, mudar papéis, excluir contas, redefinir 2FA |

## 6. Identidade visual

Identidade **"Portal"** (outubro/2026) — manual completo em [`docs/design/MARCA.md`](docs/design/MARCA.md), movimento em [`docs/design/MOTION.md`](docs/design/MOTION.md) e sistema de arte editorial em [`docs/design/KIT-EDITORIAL-SANCTA.md`](docs/design/KIT-EDITORIAL-SANCTA.md).

- **Ideia:** Sancta é o lugar onde a pessoa entra para rezar, todos os dias. O símbolo é a **porta de um santuário** com a **cruz como a luz lá dentro**; o arco nasce do "n" da Figtree Bold, por isso mora dentro do nome (`Sa[portal]cta`). No app, o arco vira moldura para imagens e orações.
- **Cores:** Azul Manto `#1F3D9C` (principal), Noite `#132760` (fundos escuros), Tinta `#0E1A3A` (texto), Ouro `#E3B65B` (só a cruz e destaques), Pergaminho `#FBF6EC` (fundos claros).
- **Tipografia:** **Figtree** (Bold para marca e títulos, Medium para a interface), embutida no app e no site; opção de leitura "Clássica" em EB Garamond.
- **Temas:** Claro (padrão), Escuro, Sépia (leitura longa) e Automático.
- **Slogan:** "Sua jornada de fé, todos os dias."
- **Nomes:** abas **Hoje · Orar · Bíblia · Igrejas · Você**; assinatura **Sancta+**; painel **Sancta Gestão**; widget **Oração do dia**.
- **Nome:** "Sancta" (latim, "santa") é curto, internacional e combina com a marca. As alternativas da referência (Amen, Nave, Lumina, Veritas, Salus, Pax, Aurea) podem nomear planos, temas ou recursos (ex.: tema "Aurea").

## 7. Riscos e cuidados

| Risco | Mitigação |
|---|---|
| **Direitos do texto bíblico** (Ave-Maria é protegida) | Obter licença da Editora Ave-Maria ou adotar outra tradução licenciada; o sistema suporta múltiplas versões |
| Política do Google Play para pagamentos | App usa só Play Billing; site vende por fora sem links no app |
| Política de uso dos mapas do OpenStreetMap | Contratar provedor de tiles ao crescer (troca pelo painel/servidor) |
| Conteúdo gerado por IA | Prompt ancorado no Catecismo, aviso de que é gerado por IA, cache revisável, recurso desligável no painel |
| LGPD | Mínimo de dados, exclusão de conta, consentimentos, textos a revisar com advogado, minimização (limpeza automática) |
| Dependência de APIs externas (liturgia, RSS) | Cache no banco, edição manual e "travar dia" no painel |
| Segurança do painel | 2FA obrigatório, IP restrito, papéis mínimos, auditoria |

## 8. Roadmap sugerido

**Lançamento (0–30 dias):** configurar integrações, revisar textos, gravar 10–20 orações em áudio, publicar em teste interno → produção gradual, campanha da Novena/Advento, importar benfeitores.

**Crescimento (1–3 meses):** lembrete diário personalizado por horário, áudio em segundo plano, jornadas de Advento e Quaresma, parcerias com paróquias (QR Code de cada igreja levando ao app), widgets na tela inicial do Android, compartilhamento de versículo como imagem.

**Expansão (3–6 meses):** iOS/App Store, plano Família, Liturgia das Horas, terço em áudio com várias vozes, grupos de oração privados, área para paróquias atualizarem os próprios horários (com verificação), conteúdo de parceiros.
