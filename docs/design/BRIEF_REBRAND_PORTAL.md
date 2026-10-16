# Brief — Rebrand "Portal" do Sancta (app, site, painel)

Documento único que orienta toda a equipe (design de marca, UI/UX do app, UI/UX do site, motion,
plataforma Android, backend, QA e segurança). **Leia inteiro antes de começar.**

- Raiz do projeto: `C:\Users\Tiago Oliveira\Desktop\sancta`
  - `backend/` Laravel 12 + Filament 5 (site, painel, API) · `app/` Flutter 3.41 · `brand/` marca · `docs/`
- Referências visuais aprovadas pelo cliente (abra as imagens!):
  - `id/sancta-A-identidade.png` — identidade (símbolo, ícone, cores, tipografia)
  - `id/sancta-A-aplicacoes.png` — aplicações (widget, tela Hoje, abertura, imagem de compartilhamento)
- Contexto completo do que já existe: `RELATORIO_DO_QUE_FOI_FEITO.md`, `SOBRE_O_PROJETO.md`, `docs/API.md`, `docs/IMPLANTACAO.md`.

---

## 1. Identidade "Portal"

**Ideia:** Sancta é o lugar onde a pessoa entra para rezar, todos os dias. O símbolo une um **n desenhado para
a marca** à porta de um santuário, com a **cruz como a luz lá dentro**. A Figtree Bold inspira a estrutura, mas
a curva e o peso são próprios do Sancta: coroa plana curta, ombros suaves e hastes reforçadas. Assim, o símbolo
mora dentro do nome (`Sa[portal]cta`). No app, **o arco vira moldura** para imagens e orações: a marca está em cada tela.

### Cores (tokens oficiais — não invente outras como cor de marca)
| Token | Hex | Uso |
|---|---|---|
| Azul Manto | `#1F3D9C` | principal (marca, botões, links, navegação ativa) |
| Noite | `#132760` | fundos escuros, superfícies do tema escuro |
| Tinta | `#0E1A3A` | texto; fundo mais profundo do tema escuro |
| Ouro | `#E3B65B` | **só a cruz e destaques** (selo Sancta+, sequência, pequenos acentos) — nunca grandes áreas |
| Pergaminho | `#FBF6EC` | fundos claros; texto/marca sobre azul |

Derivações permitidas (documente-as): tons de apoio do Azul Manto (ex.: fundo de ícone ~8–12%),
branco `#FFFFFF` para cartões sobre Pergaminho, cinzas a partir da Tinta, vermelho/verde funcionais.
Contraste mínimo **WCAG AA** (4,5:1 texto normal, 3:1 texto grande/ícones).

**Ilustrações de céu** (dentro do arco, como no mockup): degradê do Azul Manto ao pêssego/âmbar com sol
Pergaminho ("Aurora"); variações "Entardecer" e "Noite" (lua/estrelas). Usadas como capa padrão de orações
por horário/categoria quando não houver imagem.

### Tipografia
**Figtree** (OFL, gratuita). Bold 700 para marca e títulos; Medium 500 para interface; Regular 400 para
texto corrido; SemiBold 600 opcional. **Embutir os arquivos** (app e site) — nada de baixar fontes em tempo
de execução. Leitura longa (Bíblia/orações): Figtree por padrão, com opção "Clássica" (EB Garamond, já
validada no Flutter) nas configurações de leitura. ⚠️ Cormorant Garamond é proibida (acentos quebram no Flutter).
Valide acentuação (ê, ç, ã, õ, é, ú) no Flutter com um teste de renderização.

### Símbolo e logotipo
- **N/Portal** (master atual): corpo 512 × 512 u, haste de 150 u, coroa externa plana de 96 u, vão de 212 u
  com coroa plana de 48 u; ombros cúbicos. **Cruz Ouro** com haste de 52 u e travessão de 144 u, flutuando
  64 u acima da base. A porta do ícone é 512 × 666 u e reposiciona a cruz para o centro óptico do vão alto.
- **Wordmark**: "Sa" + Portal + "cta" em Figtree Bold (texto convertido em curvas). Versões: Pergaminho
  sobre azul; Azul Manto sobre claro; monocromáticas (branca/escura, cruz vazada ou na mesma cor).
- **Ícone do app**: a mesma porta em proporção vertical. Padrão (fundo Azul Manto), Escuro (fundo
  Noite/Tinta), Tingido/monocromático (Android 13 "themed icon"). Legível até 29 px.
- Área de proteção = altura do Portal. Não distorcer, girar, aplicar sombra/brilho ou trocar cores.
- **Arco como moldura**: imagens de orações/jornadas/destaques com topo em semicírculo (raio = largura/2).

### Tom de voz
Acolhedor, simples, sem jargão; pt-BR; frases curtas. Ex.: "Bom dia, Ana", "Para hoje", "Rezar agora",
"Amém". Nada de exclamações em excesso.

---

## 2. Nomenclatura (profissional e consistente em app, site e painel)

| Item | Nome |
|---|---|
| Produto | **Sancta** · slogan **"Sua jornada de fé, todos os dias."** |
| Assinatura | **Sancta+** |
| App (rótulo no celular) | **Sancta** · título na Play Store: **"Sancta: Orações e Bíblia"** |
| Painel administrativo | **Sancta Gestão** |
| Abas do app (5) | **Hoje · Orar · Bíblia · Igrejas · Você** (ícone de "Hoje" = contorno do Portal) |
| Renomear | "Rezar" → **Orar** · "Perfil" → **Você** · "Intenções da comunidade" → **Intenções** · telas de conta: "Minha conta" no site |
| Seções | Hoje, Orar, Bíblia, Liturgia diária, Jornadas, Ao vivo, Igrejas, Intenções, Notícias, Sancta+, Ajustes |
| Menu do site | Início · Bíblia · Igrejas · Ao vivo · Sancta+ · Entrar/Minha conta |
| Canais de notificação | **Avisos e novidades** (`sancta_avisos`) · **Ao vivo** (`sancta_ao_vivo`) · **Lembretes de oração** (`sancta_lembretes`) |
| Widget | **Oração do dia** |

---

## 3. Funcionalidades novas/ampliadas

### 3.1 Ao vivo (backend + painel + app + site)
- Canal: `youtube_channel_id` (UC…), `category` (missa, terco, adoracao, oracao, canal), capa.
- **Agenda** por canal (dia da semana ou todos os dias, horário, duração, título).
- **Detecção automática** (comando agendado a cada ~3 min): RSS público do canal + YouTube Data API
  (`videos.list` com `liveStreamingDetails`, ~1 unidade de cota por lote) quando houver chave cadastrada em
  **Integrações e segurança** (nova seção "YouTube", segredo criptografado + botão Testar). Sem chave: status
  pelo horário da agenda ("no horário da transmissão").
- Ao detectar início de live: push para o tópico do canal (uma vez por vídeo), canal `sancta_ao_vivo`.
- API: canais com `is_live`, `live` {video_id, title, started_at, thumbnail_url}, `upcoming`, `schedule`,
  `next_at`, `watch_url`, `topic`; e `/home` com `live_now` para o destaque "Ao vivo agora".
- App: tela redesenhada (Ao vivo agora com selo pulsante, Hoje na agenda, Canais, "Me avise" por canal via
  tópico FCM). Site: página pública **/ao-vivo**.

### 3.2 Sancta+ mais atraente (inspiração: Hallow, Calm, Headspace)
- Planos com **equivalente mensal** e **% de economia** calculados no servidor; teste grátis destacado
  (linha do tempo "Hoje → dia 5 lembrete → dia 7 cobrança" quando houver `trial_days`).
- Textos do paywall editáveis no painel (chamada, subtítulo, benefícios com ícone+título+descrição, prova
  social, perguntas frequentes, garantia/"cancele quando quiser").
- Paywall do app e página **/planos** redesenhados: herói com moldura em arco, benefícios, comparação
  Gratuito × Sancta+, planos, depoimentos (já existem no painel), FAQ.
- **Presentear Sancta+** (só no site): comprar um plano como presente (Pix/cartão) → código de presente
  (`SANC-XXXX-XXXX-XXXX`, guardado só como hash + 4 últimos), e-mail ao comprador, página **/resgatar**
  (logado) que soma o período à conta. Estorno revoga o código (ou o período, se já resgatado). Painel:
  lista de presentes (sem exibir o código), ação "Revogar".
  > Política do Google Play: o app **não** mostra compra nem resgate fora do Play Billing; o acesso
  > resgatado no site simplesmente aparece no app (conteúdo consumido).
- Pontos de entrada: cartões bloqueados com selo Ouro "Sancta+" e prévia; banner discreto em Hoje.

### 3.3 Widget Android "Oração do dia" (como no mockup)
Cartão Azul Manto, imagem em arco (aurora/noite conforme o horário), data, título, duração e botão play →
abre a oração (deep link `sancta://`). Dados gravados pelo app (home_widget) ao abrir/voltar ao app;
valor padrão sem dados.

### 3.4 Notificações
Ícone pequeno = silhueta do Portal; cor de destaque Azul Manto; os três canais da seção 2; **lembretes
diários de oração locais** (manhã/noite, horário escolhido pelo usuário, salvos também em
`notification_preferences`); push em primeiro plano exibido no app; imagem grande nas campanhas com imagem.

### 3.5 Abertura do app e motion
Splash nativo (Android 12+ SplashScreen API e versões antigas) em Azul Manto com o Portal → animação Flutter
contínua (a porta "acende", a cruz surge em Ouro, o Portal se encaixa no lugar do n e o nome se completa;
~1,2–1,6 s, nunca bloqueando o carregamento; respeita "reduzir movimento"). Microinterações: abas, play,
"Amém" (conclusão de oração com brilho do Portal + háptico), selo AO VIVO pulsante, esqueletos de
carregamento nas cores da marca, transições de página. Tokens de duração/curva centralizados.

---

## 4. Equipe, fases e "dono" de cada arquivo

| Fase | Agente | É dono de (pode criar/editar) |
|---|---|---|
| 1 | **Marca** (designer sênior de marca) | `brand/**`, `backend/public/images/**`, `backend/public/fonts/**`, `backend/public/favicon*`, `app/assets/**` (imagens, fontes, svgs), `app/lib/core/brand/**`, ícones Android (`res/mipmap-*`, `res/mipmap-anydpi-v26`, `res/drawable*/ic_notification*`, `res/drawable/sancta_portal*.xml`), `docs/design/MARCA.md` |
| 1 | **Backend** (Ao vivo + Sancta+ + Presentear) | migrations/models/services/controllers/resources/Filament novos dessas áreas, `routes/*`, `Integrations` (seção YouTube), seeders, `docs/API.md`, testes dessas áreas, views **funcionais** novas (/ao-vivo, /presentear, /resgatar) — o restyle é da fase 2 |
| 1 | **Segurança** (pentest) | correções de segurança em qualquer arquivo **com edição mínima**, `backend/tests/Feature/Security/**`, `docs/seguranca/**` |
| 2 | **UI/UX do app** | `app/lib/**` exceto: `core/brand/**` (Marca), `features/splash/**` e `core/motion/**` (Motion), `features/widget/**` e `features/notifications/**` (Android) |
| 2 | **UI/UX do site e painel** | `backend/resources/views/**`, `backend/public/css/**`, `backend/public/js/**`, tema/branding do Filament (`AdminPanelProvider` só a parte visual), templates de e-mail |
| 2 | **Motion** | `app/lib/features/splash/**`, `app/lib/core/motion/**`, `app/lib/app/splash_screen.dart`, estilos de splash Android (`res/values*/styles.xml`, `res/drawable*/launch_background.xml`, AVD do splash), `docs/design/MOTION.md` |
| 2 | **Plataforma Android** | `app/lib/features/widget/**`, `app/lib/features/notifications/**`, código nativo Kotlin, `res/layout/**`, `res/xml/*widget*`, `AndroidManifest.xml` (widget/notificações), `android/app/build.gradle.kts` (desugaring etc.), canal/cor no `backend/app/Services/Push/PushService.php` |
| 3 | **QA de integração** | testes de contrato API↔app, fluxos de pagamento, E2E no emulador, `docs/qa/**`; correções de integração |
| 3 | **Segurança (regressão)** | revisão do código novo das fases 1–2 |

Arquivos compartilhados (rotas, `app_shell.dart`, `router.dart`, `settings_screen.dart`, `pubspec.yaml`):
edite só o necessário, **releia antes de editar** e nunca desfaça mudanças de outro agente.
As dependências Flutter `flutter_svg`, `home_widget`, `flutter_local_notifications`, `timezone`,
`flutter_timezone` **já foram adicionadas** ao `pubspec.yaml`.

---

## 5. Regras para todos

1. **Segurança em primeiro lugar.** Nada de segredos no app; validar tudo no servidor; testes para cada
   regra de negócio sensível (pagamento, acesso, presente).
2. **Não quebre o que funciona**: login, pagamentos (Pix/cartão/Google Play), webhooks, Bíblia, igrejas,
   painel, integrações. Ao terminar: `php artisan test` 100% verde (backend) e `flutter analyze` sem
   problemas + `flutter test` verde (app), conforme a sua área.
3. Padrão de código: comentários em português, textos em pt-BR, Pint nos PHP alterados
   (`vendor/bin/pint <arquivos>`), `dart format` (largura 160 já configurada) nos Dart alterados.
4. Ambiente: Windows + Git Bash. Use as ferramentas Write/Edit para código (heredoc/escape no shell
   corrompe `\` e crases). Gradle precisa de
   `export JAVA_TOOL_OPTIONS=-Djdk.net.unixdomain.tmpdir=C:/Users/Public` e execução sem sandbox.
   PHP/Composer/Node/Flutter estão no PATH; **Python não**. Para SVG→PNG há `@resvg/resvg-js` em
   `C:\Users\TIAGOO~1\AppData\Local\Temp\claude\C--Users-Tiago-Oliveira-Documents-projetos-app-igreja\454b3594-673d-4210-8159-3e6e1b647d04\scratchpad\node_modules` (instale outros pacotes npm nessa pasta de rascunho, não no projeto).
5. O servidor de desenvolvimento do cliente roda em `127.0.0.1:8000` — **não o derrube**. Se precisar de
   servidor, suba outro em outra porta (8010+) e pare ao terminar. Banco local: MySQL `sancta` (root, sem
   senha); testes usam SQLite em memória. Não apague dados do banco local; limpe o que criar.
6. Downloads só de fontes oficiais e confiáveis (github.com/google/fonts, fonts.gstatic.com, registro npm
   oficial). Nada de executáveis. Nenhuma publicação externa, nenhum e-mail real, nenhuma conta criada em
   serviços de terceiros.
7. O projeto está versionado em `tech-tiago/sancta`; trabalhe em branch e mantenha as alterações de marca
   sincronizadas em `brand/source/portal.js`, `PortalGeometry`, exports Android/web e testes.
8. Não edite `.env` com segredos; novas variáveis vão para `.env.example` (e, se precisar localmente, no
   `.env` com valores de teste documentados).
9. Ao terminar, devolva um resumo: o que mudou (por arquivo/área), testes executados e resultado,
   decisões tomadas, pendências e qualquer coisa que dependa do cliente.
