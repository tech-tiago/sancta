# Relatório de QA de integração — Sancta (rebrand "Portal")

**Data:** 14/10/2026 · **Escopo:** integração app Flutter ↔ backend Laravel (contrato da API, pagamentos de ponta a
ponta, oração do período, acessibilidade, edge-to-edge, E2E no emulador, build de release).
**Ambiente:** testes automatizados (SQLite em memória) + servidor local isolado (SQLite na pasta de rascunho, portas
8014/8015, gateway de pagamento simulado) + emulador Android 16 (API 36.1, 1080×2400, navegação por gestos, fuso GMT).
Nenhum serviço externo real foi chamado com dados de teste (Mercado Pago, Google, Firebase, Gemini, YouTube simulados).
O MySQL e o servidor da porta 8000 não foram tocados.

## 1. Resultado em uma linha

| Item | Resultado |
|---|---|
| Backend `php artisan test` | **244 testes, 5.173 asserções — 100% verdes** (+19 deste QA) · Pint ok nos PHP alterados |
| App `flutter analyze` | **sem problemas** |
| App `flutter test` | **302 testes verdes** (eram 199 antes deste QA: +38 de contrato, +14 edge-to-edge, escalas 1,6×/2,0× em 23 telas, oração do período, fuso, HTTPS…) · goldens inalterados |
| `flutter build apk --debug` | ✅ |
| `flutter build apk --release` | ✅ com `SANCTA_ALLOW_DEBUG_SIGNING=true` (66,3 MB universal; 26,7 MB x86_64) · ❌ sem a variável, como esperado (trava SEC-18 do Gradle **funciona**) |
| E2E no emulador (debug e release) | ✅ roteiro completo, com **9 problemas encontrados e corrigidos** (seção 5) |
| Screenshots | `docs/qa/telas/` — 53 imagens (claro, escuro, Sépia, letra 1,6×, release, painel) |

---

## 2. Contrato API ↔ app (tarefa 1)

**Como funciona**
- `backend/tests/Feature/Contract/ApiFixturesTest.php` monta dados realistas com os **seeders do projeto** (planos,
  Bíblia completa, orações, jornadas, versículos, santos, igrejas, mídia, páginas) + registros de teste (liturgia
  travada, notícias, evento, canal ao vivo, intenções, depoimento, avisos), chama **cada endpoint que o app usa** como
  visitante, usuário gratuito e assinante (site e Google Play em teste grátis) e confere a estrutura sempre.
- Com `SANCTA_WRITE_FIXTURES=1` grava as respostas reais em `app/test/fixtures/api/*.json` (`{_contract, body}`;
  tokens trocados por um marcador; `APP_KEY` e domínio fixos — nada do `.env` real vai para os arquivos).
- `app/test/contract_test.dart` lê cada fixture com os **modelos e funções reais do app** (`AppConfig`,
  `PaywallContent`, `PrayerItem`, `PeriodPrayers`, `Journey`, `BibleChapter`, `Church`, `LiveChannel`, `PlanItem`,
  `AppUser`, `ReminderSettings`, `TrialReminder`, `WidgetContent`, `ApiException`…) e confere os campos que as telas
  leem. Um teste varre `lib/` e **falha se o app chamar um endpoint sem resposta gravada**; outro garante que todo
  tipo de bloco de conteúdo enviado pelo servidor é desenhado pelo app.

**Regerar** (pasta `backend/`): `SANCTA_WRITE_FIXTURES=1 php artisan test --filter=ApiFixturesTest` — depois, na
pasta `app/`: `flutter test test/contract_test.dart`. (Também documentado em `docs/API.md`.)

**Cobertura:** 50 endpoints · 84 respostas gravadas.

| Grupo | Endpoints (método + rota) |
|---|---|
| Público | `GET /config`, `/home`, `/liturgy`, `/bible/books`, `/bible/books/{livro}/chapters/{n}`, `/bible/search`, `/categories`, `/prayers`, `/prayers/{slug}`, `/journeys`, `/journeys/{slug}`, `/churches`, `/churches/{uuid}`, `/news`, `/news/{id}`, `/live-channels`, `/intentions`, `/plans`, `/pages/{slug}`, `/faqs`, `/announcements` · `POST /announcements/{id}/interactions` |
| Autenticação | `POST /auth/register`, `/auth/login`, `/auth/google`, `/auth/forgot-password`, `/auth/logout`, `/auth/logout-all` |
| Conta | `GET/PATCH/DELETE /me`, `PUT /me/password`, `GET /me/stats`, `POST/DELETE /me/devices`, `GET/PUT /me/bible/progress`, `GET/PUT /me/bible/annotations`, `GET /me/favorite-prayers`, `GET /me/favorite-churches`, `POST /me/prayer-sessions` |
| Ações | `POST /prayers/{slug}/favorite`, `/churches/{uuid}/favorite`, `/journeys/{slug}/steps/{n}/complete`, `/church-submissions`, `/intentions`, `/intentions/{uuid}/pray`, `/bible/explain`, `/billing/google-play/verify` |
| Erros | 401, 402 (paywall), 403 (conta suspensa), 404, 422 (campos), 429 (tentativas) → `ApiException` em português |

**Divergências encontradas e corrigidas** (ver bug 1 na seção 5): mapas vazios saíam como `[]` e derrubavam telas.

---

## 3. Fluxos de pagamento de ponta a ponta (tarefa 2)

Novo `backend/tests/Feature/Billing/PaymentFlowsTest.php` — um teste por fluxo, do "Assinar" até o conteúdo Sancta+
**liberado (ou bloqueado de novo) na API do app** (`/me`, `/home` e oração Sancta+ completa com áudio). Mercado Pago,
Google Play (API de compras, OAuth e RTDN via Pub/Sub com token OIDC) e JWKS do Google simulados com `Http::fake`.

| # | Fluxo | O que prova |
|---|---|---|
| 1 | Pix no site (gateway simulado) | valor do servidor (ignora o do formulário), QR na página, "Aprovar" → assinatura anual ativa → app liberado |
| 2 | Cartão no site (gateway simulado) | redireciona ao checkout, aprovação → app liberado |
| 3 | Pix com Mercado Pago + webhook assinado | Pix criado no MP com valor/`external_reference`/idempotência; webhook HMAC → **reconsulta no MP** → aprovado → app liberado; reenvio = `duplicate` |
| 4 | Cartão (Checkout Pro) + webhook | preferência com valor do servidor e 12x; pagamento desconhecido localizado pela referência; parcelas gravadas → app liberado |
| 5 | Cupom | desconto calculado no servidor (14.990 → 13.491) → app liberado |
| 6 | Estorno pelo MP | webhook "refunded" → acesso revogado → app bloqueado; "aprovado" atrasado não devolve o acesso |
| 7 | Google Play: teste grátis → renovação → expiração | id ofuscado vindo de `/config`; verify → **`trialing`** (sem receita), *acknowledge* chamado; outra conta não usa o token; RTDN `RENEWED` → `active` + pedido registrado; RTDN forjado → 401; repetido → `duplicate`; RTDN `EXPIRED` → app bloqueado |
| 7b | Teste grátis sem a tag | reconhecido pela fase `offerPhase.freeTrial` (correção, bug 3) |
| 8 | Presente | compra (Pix MP) → webhook → código por e-mail ao comprador (que **não** ganha acesso) → resgate por outra conta (minúsculas/espaços aceitos) → app liberado para ela → código não vale de novo → estorno → acesso dela revogado |

A cobertura que já existia (CheckoutTest, WebhookTest, GiftTest, PaymentAttackTest, CouponLimit…) foi mantida; os
novos testes amarram as pontas (site/loja → webhook → assinatura → API do app).

---

## 4. Oração do período em `/home` (tarefa 3)

- **Backend:** `period_prayers: {morning, afternoon, night}` em `/home` (mesmo formato de `featured_prayers`, com
  `locked` por pessoa). Escolha no **painel → Configurações → Aplicativo → "Oração do período"** (3 seletores de
  oração publicada; vazio = automático). Padrão: heurística no servidor (título/slug, depois categoria; prefere
  gratuitas, destaques e a ordem do painel; sem repetir). Oração despublicada volta ao automático. Cache de 5 min.
  Arquivos: `app/Services/Content/PeriodPrayers.php` (novo), `HomeController`, `Settings`, `PlatformSettings`.
  Documentado em `docs/API.md`. Testes: `tests/Feature/Api/PeriodPrayersTest.php` (6).
- **App:** tela Hoje (`_PeriodPrayerCard`) e widget/lembretes (`WidgetContent.fromHome` → `HomeWidgetSync`) usam
  `period_prayers`; sem o campo, valor `null`, malformado ou bloqueado (widget), mantêm a heurística antiga. Modelo
  `PeriodPrayers` em `models.dart`. Testes: `home_period_test.dart` (3), `notifications_widget_test.dart` (+2),
  contrato.
- **E2E:** no painel, Noite = "Oração de teste do QA" → o cartão da tela Hoje e o **widget** passaram a mostrar essa
  oração; o toque no widget abriu a oração (telas 42, 46, 47, 51, 53).

---

## 5. Bugs encontrados e corrigidos

| # | Onde apareceu | Problema | Correção (arquivos) | Teste de regressão |
|---|---|---|---|---|
| 1 | Contrato | O PHP serializa array associativo **vazio** como `[]`: `prayers` da liturgia (dia sem orações), `contacts`/`schedules` de igreja sem horários/contatos e `notification_preferences` salvo vazio. O app lê como mapa (`as Map?`) → **erro e tela quebrada** (liturgia, detalhe da igreja e até o login, via `/me`) | Backend devolve `{}` (`LiturgyController`, `ChurchResource`, `UserResource`); app tolerante a servidores antigos (`_m`/`_l` em `models.dart`, `liturgy_screen.dart`, `content_providers.dart`) | `ApiFixturesTest` (`assertJsonObject`), `contract_test.dart` ("Servidores antigos") |
| 2 | Suíte do backend | `GiftTest::test_redeem_form_requires_csrf` muda o ambiente e deixava **hosts confiáveis estáticos** do Symfony para os testes seguintes (falha só na suíte completa) | Limpeza no próprio teste (`GiftTest.php`) e no `ApiFixturesTest` | suíte completa verde |
| 3 | Google Play | Teste grátis só era reconhecido pela **tag `trial`** da oferta; sem a tag, ficava "ativa" e o app não agendava o aviso "termina em 2 dias" | Também pela fase `offerPhase.freeTrial` (`GooglePlayBilling.php`); passo a passo da tag em `docs/IMPLANTACAO.md` | `PaymentFlowsTest` 7 e 7b |
| 4 | Acessibilidade | Letra limitada a 1,3×. A 1,6×/2,0×: estouro no cabeçalho da Liturgia e nos slides das boas-vindas; textos cortados/palavras quebradas ("Liturgia…", "Automáti-co", "Assisti-r no YouTu-be", "06:/00", "Doming-o"); carrosséis presos em 1,3× cortavam títulos | Limite **2,0×** (`app.dart`, `maxTextScale`); `largeText`/`carouselHeight` (`common.dart`); ajustes em `liturgy_screen`, `welcome_screen`, `library_screen`, `settings_screen`, `live_screen`, `church_detail_screen`, `paywall_screen`, `home_screen` | `a11y_test.dart`: 23 telas a 1,3×/1,6×/2,0× + 360×640 a 2,0× |
| 5 | Edge-to-edge | Ligado em todas as versões (`main.dart`, barras transparentes em `core/theme/system_bars.dart`). No emulador: a **barra de ações do versículo ficava sob a barra de gestos** (o `Scaffold` tira o recuo do `bottomSheet`); listas sem recuo inferior; telas com topo Azul Manto com ícones escuros | Recuo explícito (`chapter_screen`), `+ paddingOf(context).bottom` em favoritos, anotações, busca, liturgia, intenções e capítulo; `DarkSystemBars` em boas-vindas, cabeçalho de login/cadastro, herói do Sancta+ e bloqueio de versão | `edge_to_edge_test.dart` (14; simula status 24 dp + navegação 48 dp) |
| 6 | Ao vivo (emulador em GMT) | "Hoje na agenda" comparava os horários (cadastrados em **horário de Brasília**) com o relógio local do aparelho: "AGORA"/"encerrada" errados fora de UTC−3 e contraditórios com o "no horário" do servidor | `brasiliaWallClock` + rótulo "(horário de Brasília)" quando o fuso difere (`live_screen.dart`) | `models_test.dart` |
| 7 | Lembretes (emulador em GMT) | Fuso "GMT"/"UTC" não existe na base compacta → caía em São Paulo: lembrete das **07:00 agendado para as 10:00** do aparelho | Apelidos de UTC e fuso fixo pelo deslocamento atual (`local_timezone.dart`). Conferido no aparelho: alarme às 07:00 locais | `notifications_widget_test.dart` |
| 8 | Boas-vindas | **"Explorar sem conta" não saía da tela** (o roteador não tira visitantes das telas públicas) | Navega para Hoje após marcar o modo visitante (`welcome_screen.dart`) | `navigation_test.dart` |
| 9 | Build de release | O APK de release **conversou por HTTP** com o servidor local: o `network_security_config` do Android **não se aplica ao HttpClient do Dart** (a premissa "HTTPS obrigatório pelo Android" era falsa para o Flutter) | `ApiClient` recusa URL sem HTTPS no release, antes de ler o token (`api_client.dart`). Conferido: release com `http://` mostra "Você está sem conexão" e nada chega ao servidor | `unit_test.dart` |

Também verificado: a trava do Gradle (SEC-18, agente de segurança) — release **falha** sem `key.properties` e passa
com `SANCTA_ALLOW_DEBUG_SIGNING=true`.

---

## 6. Matriz do E2E no emulador

Servidor isolado: `DB_CONNECTION=sqlite` (pasta de rascunho), `migrate --seed` (inclui a migration nova de cupons),
cache/sessão no próprio SQLite, e-mail em memória, liturgia local (API externa bloqueada), Mercado Pago/Google/
Firebase/Gemini sem credenciais. Contas de teste criadas só nesse SQLite (credenciais na pasta de rascunho, fora do
projeto). APK de depuração com `API_BASE_URL=http://10.0.2.2:8014`.

| Área | Caso | Resultado | Tela |
|---|---|---|---|
| Abertura | Splash nativo → porta acende → wordmark (sem quadro branco; Portal na mesma posição) | ✅ debug e release | 01, 02 |
| Abertura | Abertura a frio (release, 2ª execução) | ✅ ~1,35–1,48 s (`am start -W`) | — |
| Conta | Cadastro (termos obrigatórios, senha forte), permissão de notificação, pop-up de boas-vindas | ✅ (obs. 2) | 03, 04, 06 |
| Conta | Login com senha errada (mensagem genérica) e correta; sair da conta; login de assinante | ✅ | 05 |
| Conta | "Explorar sem conta" | ❌→✅ bug 8 | — |
| Hoje | Saudação, oração do período, Para hoje (ao vivo no horário, Evangelho, versículo), destaques, eventos, notícias | ✅ | 07–09 |
| Orar | Categorias, oração, favoritar, "Amém" com celebração e sequência (1º dia) | ✅ (obs. 1) | 10–13 |
| Jornada | Novena: abrir dia 1, concluir ("Dia concluído"), progresso 1/9, "Continuar: dia 2" | ✅ | 14, 15 |
| Bíblia | Livros AT/NT, João 3, seleção, destaque dourado, nota, busca "Nicodemos" (5), "Continuar leitura" | ✅ (bug 5 na barra de ações) | 16–21 |
| Bíblia | "Explicar" como gratuito → paywall; como assinante → (Gemini não configurado no ambiente) | ✅ / pendente | — |
| Liturgia | Leituras em abas, cor, orações, reflexão Sancta+ | ✅ | 22, 23 |
| Igrejas | Lista com próxima missa, mapa (OpenStreetMap), sugestão enviada para moderação | ✅ | 24–28 |
| Ao vivo | Ao vivo agora / no horário, agenda, canais, "Me avise" (tópico) | ✅ (bug 6) | 29, 30 |
| Você / Ajustes | Perfil, temas Claro/Escuro/Sépia/Automático, fonte Clássica, lembretes manhã/noite | ✅ (bug 7) | 31–37 |
| Sancta+ | Paywall; compra no emulador → "Plano indisponível na loja no momento." (elegante, sem travar) | ✅ | 38, 39 |
| Painel ↔ app | Oração criada no painel aparece no app; aviso/pop-up criado no painel aparece e o botão (`sancta://oracao/...`) abre a oração; texto do paywall alterado no painel aparece no app; oração da noite escolhida no painel vai para Hoje e widget | ✅ | 40, 42–44, 53 |
| Assinante | Conteúdo Sancta+ liberado (Exame de consciência completo), selo no perfil | ✅ | 33, 41 |
| Widget | Adicionado à tela inicial; mostra a oração do período e abre a oração | ✅ debug e release | 45–47, 51 |
| Deep links | `adb shell am start -d "sancta://…"` (válidos e inválidos) | ⚠️ o app **não registra** o esquema para outros apps (decisão de segurança: `flutter_deeplinking_enabled=false`, sem intent-filter) → todos dão "unable to resolve Intent". Links internos (aviso, widget, push) funcionam | 44, 47 |
| Sistema | Modo escuro do sistema (tema Automático) | ✅ | 09, 11, 17, 25, 32 |
| Sistema | Letra do sistema 1,6× (`font_scale`) | ✅ (bug 4) · voltou para 1,0 | 48–50 |
| Release | Abertura, Hoje, widget, lembrete agendado, sem erros de R8/ofuscação no logcat | ✅ | 51 |
| Release | HTTPS obrigatório | ❌→✅ bug 9 | 52 |

**Checklist do MOTION.md §7:** 1 ✅ no Android 16 (sem quadro branco; Portal sem "pulo"); Android < 12 sem
imagem disponível. 2 e 3 (3 botões; abrir por notificação) e 5 (Remover animações) dependem de mudar ajustes do
sistema/FCM — ficaram para o aparelho (cobertos pelos testes automáticos de motion). 4 parcial (release sem rede
mostra o estado offline depois da abertura). 6 parcial (rótulos conferidos pelo uiautomator; TalkBack não ligado).
7 fluidez: emulador não representa aparelho de entrada.

---

## 7. Observações e recomendações (não corrigidas)

1. **"Amém" espera a rede:** a celebração só aparece depois de gravar a sessão e atualizar o perfil (até 4 s de
   espera). Em rede lenta o retorno do toque atrasa — recomendo mostrar a celebração na hora e gravar em paralelo.
2. **Cadastro:** a mensagem "Aceite os Termos…" continua visível depois de marcar a caixa, até reenviar.
3. **Lembretes inexatos:** o Android agenda com janela de até 1 h (sem permissão de alarme exato) — o lembrete das
   07:00 pode chegar até 08:00. Aceitável; vale explicar na tela ou usar `inexactAllowWhileIdle`/alarme exato.
4. **Deep links externos:** para links do site/e-mail abrirem o app (`sancta://…` ou App Links `https://`), será
   preciso intent-filter + tratamento no roteador, mantendo a lista fechada de `deepLinkToRoute`.
5. **Documentos de segurança** (`RELATORIO_DO_QUE_FOI_FEITO.md` §4, `docs/seguranca/PENTEST.md`) ainda dizem que o
   Android bloqueia HTTP no release — falso para o Flutter; a proteção agora está no `ApiClient` (bug 9).
6. **Fixtures de goldens e fuso:** os goldens usam o relógio local fixo; em uma máquina de CI fora de UTC−3 a tela
   "Ao vivo" pode mudar de estado (agenda em horário de Brasília).
7. **Emulador com pouco espaço** (partição de 6 GB a 90%): o APK de depuração tem ~170 MB; foi preciso reinstalar e
   reiniciar o emulador. A configuração do AVD foi mantida como estava.
8. Servidor local (`php artisan serve` + SQLite no Windows) responde em 0,5–2 s (cadastro ~10 s): limitação do
   ambiente de teste, não do produto.

## 8. Só com credenciais ou aparelho reais

| Integração | O que falta validar |
|---|---|
| **Firebase Cloud Messaging** | push real ("Ao vivo agora", campanhas), assinatura de tópico do "Me avise", toque na notificação com o app fechado (MOTION §7.3) |
| **Google Play Billing** | compra real/teste grátis com conta de teste da Play Console, restaurar compras, RTDN pelo Pub/Sub, upgrade/downgrade, oferta com a tag `trial` |
| **Mercado Pago (produção)** | Pix real (QR do banco), Checkout Pro com cartão, assinatura secreta do webhook gerada pelo MP, estorno pelo painel do MP |
| **YouTube Data API** | detecção de live real e push automático (comando `sancta:live-sync`) |
| **Gemini** | explicação de versículo para assinante |
| **Login com Google** | `id_token` real (client ID Web do painel) |
| **SMTP** | e-mails (verificação, recuperação, código do presente) |
| **Aparelhos** | Android < 12 (splash antigo), Android 12–14 com 3 botões, TalkBack, "Remover animações", desempenho em aparelho de entrada, iOS |

## 9. Arquivos alterados neste QA

**Backend:** `app/Services/Content/PeriodPrayers.php` (novo) · `app/Services/Settings.php` ·
`app/Http/Controllers/Api/V1/HomeController.php` · `app/Http/Controllers/Api/V1/LiturgyController.php` ·
`app/Http/Resources/ChurchResource.php` · `app/Http/Resources/UserResource.php` ·
`app/Filament/Pages/PlatformSettings.php` · `app/Services/Billing/GooglePlayBilling.php` ·
`tests/Feature/Contract/ApiFixturesTest.php` (novo) · `tests/Feature/Billing/PaymentFlowsTest.php` (novo) ·
`tests/Feature/Api/PeriodPrayersTest.php` (novo) · `tests/Feature/Billing/GiftTest.php` (isolamento).

**App:** `lib/app/app.dart` · `lib/main.dart` · `lib/core/theme/system_bars.dart` (novo) ·
`lib/core/models/models.dart` · `lib/core/state/content_providers.dart` · `lib/core/network/api_client.dart` ·
`lib/core/widgets/common.dart` · `lib/features/home/home_screen.dart` · `lib/features/widget/widget_content.dart` ·
`lib/features/liturgy/liturgy_screen.dart` · `lib/features/auth/welcome_screen.dart` ·
`lib/features/auth/auth_scaffold.dart` · `lib/app/status_gate.dart` · `lib/features/premium/paywall_screen.dart` ·
`lib/features/prayers/library_screen.dart` · `lib/features/profile/settings_screen.dart` ·
`lib/features/profile/favorites_screen.dart` · `lib/features/live/live_screen.dart` ·
`lib/features/churches/church_detail_screen.dart` · `lib/features/bible/chapter_screen.dart` ·
`lib/features/bible/annotations_screen.dart` · `lib/features/bible/search_screen.dart` ·
`lib/features/community/intentions_screen.dart` · `lib/features/notifications/local_timezone.dart`.
Testes: `test/contract_test.dart` (novo) · `test/fixtures/api/*.json` (84, novos) ·
`test/goldens/home_period_test.dart` (novo) · `test/goldens/edge_to_edge_test.dart` (novo) ·
`test/goldens/a11y_test.dart` · `test/goldens/harness.dart` · `test/goldens/fixtures.dart` ·
`test/goldens/navigation_test.dart` · `test/notifications_widget_test.dart` · `test/models_test.dart` ·
`test/unit_test.dart`.

**Documentos:** `docs/API.md` (period_prayers, mapas `{}`, contrato) · `docs/IMPLANTACAO.md` (tag `trial`) ·
`docs/design/MOTION.md` (escala 2,0× e edge-to-edge) · `docs/qa/RELATORIO_QA.md` · `docs/qa/telas/` (53).
