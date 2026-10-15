# Relatório do que foi feito — Rebrand e reconstrução do app (Sancta)

**Data:** 03/10/2026 · **Pasta do projeto:** `Área de Trabalho/sancta`

## 1. Resumo

O app "Paróquia Nossa Sra. da Saúde" foi analisado por completo e **reconstruído do zero** como **Sancta** — um app católico no estilo do Hallow, com assinatura (Sancta+), totalmente gerenciado por um painel administrativo em Laravel, e um site público.

| Entrega | Situação |
|---|---|
| Análise completa do app antigo (código, dados, segurança) | ✅ Concluída — 12 problemas relevantes encontrados (seção 2) |
| Nova marca (logo, ícones, banner, paleta, temas) | ✅ Substituída pela identidade **"Portal"** em out/2026 (seção 13) |
| Backend Laravel 12 + MySQL (API, site, painel) | ✅ 49 tabelas, migrations e seeders funcionando no MySQL 8.4 |
| Painel administrativo (Filament 5) com 25 cadastros, dashboard e configurações | ✅ 43 telas testadas no navegador |
| Site público (apresentação, Bíblia, mapa de igrejas, planos Pix/cartão, conta) | ✅ Testado no navegador nos temas claro e escuro |
| App Flutter reconstruído (login novo, 4 temas, Bíblia, orações, jornadas, igrejas, assinatura) | ✅ `flutter analyze` sem problemas, 302 testes passando |
| Segurança e testes automatizados | ✅ 245 testes / 5.177 asserções no backend — todos passando; dois pentests (seção 13); `composer audit` sem vulnerabilidades |
| Relatórios e documentação | ✅ Este arquivo, `SOBRE_O_PROJETO.md`, `README.md`, `docs/` |

---

## 2. Análise do app antigo

**Inventário:** Flutter, 30 arquivos Dart (~9.500 linhas), Firebase Auth (Google) + Firestore, Bíblia Ave-Maria em JSON de 6,7 MB embutido no APK, 14 Orações Eucarísticas + Via-Sacra em JSON, igrejas num JSON hospedado em `alureacademico.com.br`, liturgia de uma API externa, notícias raspadas do HTML do Vatican News, doações via Mercado Pago por um PHP externo, abas Mapa · Notícias · Igreja · Live · Mais.

### Problemas encontrados

| # | Gravidade | Problema | Como foi resolvido no Sancta |
|---|---|---|---|
| 1 | **Crítica** | **Chave da API do Gemini escrita no código** (`biblia_page.dart`) — qualquer pessoa extrai do APK e usa a sua cota/cartão. | A IA agora roda **somente no servidor**; o app nunca vê a chave. **Ação sua: revogar a chave antiga** (ver seção 8). |
| 2 | **Alta** | Doação enviava `uid` e valor para um PHP externo **sem autenticação** — qualquer um podia gerar cobranças em nome de outro usuário; níveis de doador gravados no Firestore. | Assinaturas com valor calculado no servidor, webhooks assinados, reconsulta ao provedor, registros imutáveis no MySQL. |
| 3 | **Alta** | Cadastro de igreja enviado a um PHP de e-mail **sem autenticação, sem limite e com imagem em base64** (porta aberta para spam e abuso). | Fila de moderação no painel, limite por usuário/IP, validação do arquivo real, honeypot e tempo mínimo no site. |
| 4 | Alta | Dados espalhados (Firestore, JSON em outro domínio, assets) e **nada gerenciável** sem publicar nova versão. | Tudo no MySQL e editável no painel; o app recebe tudo pela API. |
| 5 | Média | Mensagens de erro com detalhes técnicos para o usuário ("Detalhe técnico: …") e `print` de dados. | Erros amigáveis em português; detalhes só no log do servidor; `ErrorWidget` neutro em produção. |
| 6 | Média | Notícias por **raspagem de HTML** do Vatican News (quebra a cada mudança do site). | Leitor de RSS no servidor com cache, deduplicação, proteção SSRF/XXE e fontes gerenciadas no painel. |
| 7 | Média | APK pesado: Bíblia de 6,7 MB e todas as orações embutidas. | Conteúdo sob demanda com cache local; APK menor e conteúdo sempre atualizado. |
| 8 | Média | Arquivos gigantes misturando tela, dados e regras (ex.: `horarios_page.dart` com 1.596 linhas). | Arquitetura por funcionalidades (core/features), estado com Riverpod, rotas com go_router. |
| 9 | Média | Só tema escuro (o interruptor "Modo escuro" não tinha efeito real). | 4 temas: Automático, Claro (Aurora), Escuro (Vigília) e Pergaminho (leitura), sincronizados com a conta. |
| 10 | Baixa | Dependências desnecessárias (google_places_flutter, carousel_slider, image_picker, flutter_launcher_icons em produção…). | Dependências enxutas e atualizadas. |
| 11 | Baixa | Sem exclusão de conta acessível (exigência do Google Play) e política de privacidade genérica. | Exclusão de conta no app e no site + página `/excluir-conta`; textos LGPD iniciais editáveis. |
| 12 | Info | Keystore de upload dentro da pasta do projeto (`android/app/upload-keystore.jks`), ignorada pelo Git, porém sem cópia de segurança documentada. | Guia de assinatura com `key.properties` fora do repositório e recomendação de backup offline. |

---

## 3. O que foi construído

### 3.1 Identidade visual (`brand/`)
- **Nova marca:** "S" caligráfico (inspirado numa estola) com a cruz acima e um halo aberto — diferente da referência, mantendo a ideia de cruz + S + halo. Dourado envelhecido sobre azul-noite.
- Gerados: SVG (colorido, monocromático, horizontal claro/escuro), ícone do app 1024/512, **ícones Android em 5 densidades + ícone adaptativo + ícone de notificação**, ícone e **banner da Play Store** (1024×500), favicon, ícone Apple, **imagem para redes sociais** (1200×630).
- Tagline: **"Ora · Lê · Vive"** (editável no painel). A referência usava "Ora · Leia · Viva", que mistura formas verbais; a versão adotada é gramaticalmente consistente (outras opções em `SOBRE_O_PROJETO.md`).

### 3.2 Backend (`backend/`) — Laravel 12, PHP 8.2, MySQL 8
- **49 tabelas** com migrations e **seeders**: usuários/papéis/2FA, planos, cupons, assinaturas, pagamentos, webhooks, categorias, orações (texto, blocos litúrgicos e áudio), jornadas/novenas/planos de leitura e progresso, versículo do dia, liturgia, santos, eventos, sessões de oração (sequência de dias), intenções, Bíblia (versões, livros, 35.450 versículos com índice FULLTEXT, anotações, progresso, cache de IA), igrejas com **horários estruturados**, favoritos e sugestões, fontes/notícias, canais ao vivo, avisos/pop-ups e métricas, campanhas de push, páginas, FAQ, depoimentos, configurações, dispositivos e auditoria.
- **Migração dos dados do app antigo:** Bíblia completa (73 livros), as 14 Orações Eucarísticas e a Via-Sacra (convertidas para blocos editáveis), as 5 igrejas com horários convertidos de texto livre para dia/hora estruturados, os canais ao vivo (Canção Nova, Aparecida, Frei Gilson).
- **Conteúdo inicial novo:** 18 orações tradicionais e guiadas (3 exclusivas Sancta+), 9 categorias, 3 jornadas (Novena a N. Sra. Aparecida — que começa justamente hoje, 03/10 —, "7 dias para começar a rezar", "Evangelho de João em 21 dias" Sancta+), versículo do dia para 60 dias, santos de outubro, 3 planos, 8 perguntas frequentes, páginas de Termos, Privacidade (LGPD), Exclusão de conta e Sobre.
- **API v1** com 50+ rotas (ver `docs/API.md`).
- **Pagamentos:** Pix (QR Code na página, confirmação automática) e cartão em até 12x (Checkout do Mercado Pago — o cartão nunca passa pelo servidor), cupons, acúmulo de períodos pré-pagos, estornos que revogam o acesso; **Google Play Billing** com validação na API do Google, vínculo da compra à conta e notificações em tempo real (RTDN).
- **Gateway simulado** para demonstrar o fluxo completo sem credenciais (só funciona em ambiente local; bloqueado em produção).
- **Rotinas agendadas:** notícias (15 min), liturgia (diária), manutenção de assinaturas e lembretes de renovação (hora em hora), push agendado (cada minuto), limpeza de dados antigos.
- **Importação dos usuários antigos** com cortesia de Sancta+ para quem doou no app antigo (gratidão aos benfeitores).

### 3.3 Painel administrativo (Filament 5) — `/painel`
- **Dashboard:** usuários e novos cadastros, assinantes e conversão, **receita do mês vs. mês anterior**, **MRR**, pessoas rezando hoje, moderação pendente, gráficos de receita (12 meses), cadastros (30 dias), assinantes por plano, formas de pagamento e últimos pagamentos.
- **Assinaturas:** Planos (preços, períodos, teste grátis, parcelas, IDs do Google Play, onde vender), Assinaturas (quem é assinante, origem, até quando, filtros "vencendo em 7 dias"; **conceder cortesia** e **revogar**), Pagamentos (somente leitura, totais, filtros por período/forma/status, consulta ao provedor), Cupons.
- **Usuários:** busca, filtros (assinante, papel, situação, origem), edição, **conceder Sancta+, suspender/reativar, encerrar sessões, redefinir 2FA, excluir conta (LGPD)**, histórico de assinaturas e pagamentos por usuário. Criação de membros da equipe (somente super admin).
- **Conteúdo:** Orações (editor rico, **blocos litúrgicos visuais** — rubrica, sacerdote, assembleia, diálogo, posturas, seções recolhíveis —, áudio guiado, agendamento, rascunho, duplicar), Categorias, Jornadas (etapas com meditação, leitura bíblica, oração e áudio), Versículo do dia, Liturgia (importação automática + edição/trava + reflexão Sancta+), Santos do dia, Eventos.
- **Igrejas:** cadastro completo com **horários por tipo e dia da semana**, coordenadas, contatos, verificação; **Sugestões e correções** com aprovação que **publica automaticamente no mapa** (com ajuste dos dados antes de aprovar) ou recusa; **Intenções de oração** com moderação individual e em lote. Selos com contagem de pendências no menu.
- **Comunicação:** **Avisos e pop-ups** (pop-up, banner ou tela cheia; categoria evento/promoção/aviso/novidade; público todos/gratuitos/assinantes/visitantes; período; frequência; prioridade; botão com link interno `sancta://` ou externo; métricas de visualização, cliques e CTR) e **Notificações push** (envio imediato ou agendado por público).
- **Mídia:** Notícias (manuais ou importadas), Fontes RSS (com "Buscar agora" e registro de erros), **Ao vivo** (canais/links do YouTube).
- **Site:** Páginas (termos, privacidade…), Perguntas frequentes, Depoimentos.
- **Sistema:** **Configurações da plataforma** (identidade, links, versão mínima/forçar atualização, modo manutenção, liga/desliga de recursos, tema padrão, textos do Sancta+, painel de status das integrações e da segurança), **Auditoria** (quem fez o quê, antes/depois, IP) e **Webhooks recebidos**.
- **Tema claro/escuro/automático** no painel, cores e logo da marca, rótulos em português.

### 3.4 Site público
- Página inicial de vendas (hero com mockup do app, recursos, "um dia com o Sancta", Sancta+ com planos, Bíblia e igrejas, FAQ, depoimentos quando houver, chamada para a Play Store).
- **Bíblia online** completa com busca (full-text no MySQL) e navegação entre capítulos.
- **Mapa de igrejas** (Leaflet auto-hospedado + OpenStreetMap, agrupamento de marcadores, busca, "perto de mim", tema escuro) e página de cada igreja com horários; **cadastrar igreja** e **corrigir informação** com moderação.
- **Planos e checkout** Pix/cartão, página de pagamento com QR Code e atualização automática, área "Minha conta" (assinatura, pagamentos, tema, senha, exclusão de conta), login/cadastro/recuperação de senha, verificação de e-mail.
- Páginas de erro próprias, `sitemap.xml`, metatags sociais, tema claro/escuro com detecção automática.

### 3.5 Aplicativo Flutter (`app/`)
- **Mesmo `applicationId` (`com.maria.app_igreja`)**, versão **3.0.0+8** → chega como atualização para os usuários atuais.
- **Login totalmente reformulado:** tela de boas-vindas com carrossel, **Continuar com Google** (validado no servidor), e-mail/senha, cadastro com aceite de termos, recuperação de senha e **modo visitante** (explorar sem conta, com convite para entrar ao usar recursos pessoais).
- **Abas:** Hoje · Rezar · Bíblia · Igrejas · Perfil.
- **Hoje:** saudação, sequência de dias, banner do painel, versículo do dia com reflexão, liturgia, continuar jornada, atalhos, jornadas, orações em destaque, santo do dia, ao vivo, eventos, notícias, convite Sancta+.
- **Rezar:** busca, categorias, jornadas/novenas, orações com renderização litúrgica (falas, respostas, rubricas em vermelho, posturas), **áudio guiado**, favoritos, tamanho do texto, "Concluir oração" (alimenta a sequência).
- **Jornadas:** progresso por dia, meditação, leitura bíblica, oração vinculada, conclusão.
- **Bíblia:** continuar leitura, índice AT/NT, leitor com **destaques em 5 cores, notas, marcadores, copiar, compartilhar, explicação por IA (Sancta+), ouvir em voz alta**, busca por palavra ou referência ("Jo 3,16"), minhas anotações.
- **Liturgia** por data com cor litúrgica, leituras em abas, orações da Missa e reflexão Sancta+.
- **Igrejas:** mapa, perto de mim, próxima missa, detalhes com horários e contatos (ligar, WhatsApp, e-mail, Instagram, rotas), favoritar, sugerir/corrigir.
- **Comunidade:** intenções de oração ("Rezei · N").
- **Perfil:** estatísticas e calendário da semana, assinatura, favoritos, anotações, **configurações (4 temas, tamanho de leitura, preferências de notificação, nome, senha, sair de todos os aparelhos, excluir conta)**, ajuda/FAQ, páginas legais.
- **Sancta+:** paywall com preços reais da Play Store, teste grátis, restaurar compras.
- **Avisos/pop-ups do painel** respeitando frequência e público; **push** com tópicos por público e deep links; **bloqueio por versão mínima e modo manutenção** controlados pelo painel; cache offline.
- **Doações e loja foram removidas**, como pedido — substituídas pela assinatura.

---

## 4. Segurança aplicada

**Backend / site / painel**
- Senhas com bcrypt; política mínima (8+, letras e números) e, em produção, **bloqueio de senhas vazadas** (Have I Been Pwned por k-anonimato).
- Tokens da API por aparelho, com **expiração**, limite de aparelhos, revogação ao trocar senha/suspender.
- **Limites de tentativa** em login (5 por e-mail+IP), cadastro, recuperação de senha, busca, envios, IA, uploads, checkout e webhooks.
- Respostas genéricas que **não revelam se um e-mail existe**; tempo constante na verificação de senha.
- **Proteção contra atribuição em massa** (papel/situação nunca vêm do formulário; em desenvolvimento qualquer tentativa gera erro).
- **IDs públicos em UUID** e consultas sempre filtradas pelo dono (sem IDOR em pagamentos, anotações, etc.).
- **Conteúdo Sancta+ bloqueado no servidor**, não apenas escondido no app.
- **HTML sanitizado** ao salvar e ao exibir (anti-XSS); o app renderiza blocos, sem WebView.
- **Cabeçalhos de segurança:** CSP com *nonce* no site, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS em produção; CSP restritiva na API.
- **CSRF** no site e no painel; sessão criptografada, *HttpOnly*, *SameSite*; regeneração de sessão no login.
- **Pagamentos:** valor calculado no servidor, cupom validado no servidor, webhook do Mercado Pago com **HMAC + anti-replay + idempotência + reconsulta**, Google Play com **verificação na API do Google, vínculo à conta (identificador ofuscado) e token OIDC no RTDN**; divergência de valor bloqueia a liberação.
- **Painel:** caminho configurável, **restrição por IP** (responde 404 fora da lista), **2FA (TOTP) com códigos de recuperação**, papéis com políticas por recurso, financeiro somente leitura, auditoria de todas as ações, `noindex`.
- **Uploads** validados pelo conteúdo real, nomes aleatórios, imagens de sugestões em disco privado até a aprovação.
- **SSRF/XXE** bloqueados no leitor de RSS; Leaflet auto-hospedado com verificação de integridade (SRI).
- Exclusão de conta que **anonimiza** dados pessoais e mantém registros financeiros (obrigação legal).

**App**
- Nenhum segredo no APK (apenas URL e IDs públicos); **token no Keystore** (armazenamento seguro); **HTTPS obrigatório** (no release, o próprio cliente HTTP do app recusa URLs sem HTTPS — a regra de rede do Android não vale para o cliente do Flutter —; HTTP só para o servidor local no build de depuração); **backup desativado**; **R8/ofuscação** no release; deep links aceitam somente `sancta://` com rotas conhecidas; Firebase Analytics e ID de publicidade desligados.

---

## 5. Testes executados

| Verificação | Resultado |
|---|---|
| PHPUnit (backend) | **93 testes · 395 asserções · 100% aprovados** |
| — Autenticação | cadastro, envio do e-mail de confirmação, senha forte, e-mail duplicado, mensagem genérica, limite de tentativas (429), token com expiração, logout, conta suspensa, troca de senha revoga sessões, exclusão LGPD, login Google com token falso |
| — Escalada de privilégio | papel não pode ser enviado no cadastro, no perfil da API nem no site |
| — Conteúdo Sancta+ | oração/jornada bloqueadas para visitante e gratuito, liberadas para assinante; rascunhos invisíveis; IA exige assinatura e usa chave só no servidor (com cache) |
| — IDOR | anotações isoladas por usuário; pagamento de outra pessoa → 404; igreja oculta não aparece nem pode ser favoritada |
| — Pagamentos | preço adulterado ignorado, aprovação idempotente, valor divergente não libera, referência alheia ignorada, estorno revoga, períodos acumulam, cupom válido/vencido, simulador bloqueado fora do ambiente local |
| — Webhooks | sem assinatura, assinatura forjada e evento antigo (replay) → 401; válido confirma no provedor; repetido → ignorado; payload mentiroso não libera; RTDN sem/forjado → 401 |
| — Painel (RBAC) | visitante → login; usuário comum → 403; conta suspensa → 403; editor sem acesso a finanças/usuários; moderador só moderação; pagamentos somente leitura; só super admin muda papéis; IP fora da lista → 404; `noindex` |
| — Proteções web | cabeçalhos/CSP, sanitização de HTML, SSRF (localhost, redes privadas, metadados da nuvem, `file://`), CSRF, honeypot e tempo mínimo, aprovação obrigatória de sugestões, correção altera só a igreja certa, upload falso rejeitado |
| `composer audit` | nenhuma vulnerabilidade conhecida |
| `flutter analyze` | nenhum problema |
| `flutter test` | 11 testes aprovados (deep links seguros, versão mínima, validações, referências bíblicas, modelos, configuração remota) |
| Teste manual no navegador | site (claro/escuro), Bíblia, mapa, painel: login e **43 telas** abertas com sucesso |
| MySQL 8.4 | `migrate:fresh --seed` completo sem erros |

Problemas encontrados **durante os testes e corrigidos:** conflito do componente de ícones com o pacote do Filament, título da página vazando de um laço, closures do Filament com nome de parâmetro incorreto (500 em Assinaturas/Pagamentos), rótulo de horário com enum, provedor de mapas CARTO passou a exigir chave (trocado por OpenStreetMap), numeração dos Salmos da Ave-Maria (Vulgata) nas referências do versículo do dia, marcadores `*` de nota de rodapé no texto bíblico, motor MyISAM padrão do seu MySQL (forçado InnoDB), e-mail de confirmação de cadastro que não era disparado (corrigido e coberto por teste), permissões de biometria desnecessárias herdadas de uma dependência (removidas do APK).

---

## 6. Decisões técnicas (e por quê)

- **Filament para o painel:** painel profissional, responsivo, com tema claro/escuro, 2FA e tabelas/filtros prontos — muito mais seguro e rápido de evoluir do que um painel feito à mão.
- **Site em Blade + CSS próprio (sem build de Node):** implantação simples em hospedagem comum e carregamento rápido.
- **Pix e cartão no site, Google Play Billing no app:** é o que a política do Google Play exige para conteúdo digital vendido dentro do app; a mesma conta vale nos dois canais.
- **Planos do site pré-pagos (sem renovação automática):** evita cobranças recorrentes inesperadas em cartão/Pix e simplifica o suporte; renovação automática fica no Google Play.
- **OpenStreetMap:** sem custo e sem chave no app; a URL dos mapas é configurável pelo servidor para trocar de provedor sem nova versão.
- **Riverpod + go_router + Dio** no app: padrão de mercado, testável e leve.
- **Firebase mantido apenas para push** (Auth e Firestore removidos): menos dependências e dados centralizados no seu servidor.

---

## 7. Dados de acesso local (somente ambiente de desenvolvimento)

- Painel: `http://localhost:8000/painel` (credenciais e segurança em **Sistema → Integrações e segurança**, só super administrador) — usuário `admin@sancta.test`, senha definida em `backend/.env` (`SANCTA_ADMIN_PASSWORD`). **Troque antes de qualquer uso real.**
- Banco local: `sancta` no MySQL da porta 3306. Os **60 usuários e pagamentos de demonstração** (e-mails `@demo.sancta.test`) existem só no ambiente local para popular os gráficos.
- Pagamentos simulados: no checkout local aparece "Ambiente de demonstração — Aprovar/Recusar".
- Conta de teste do app (usada no emulador): `teste.emulador@sancta.test`, senha em `backend/.env` (`SANCTA_E2E_USER_PASSWORD`).

---

## 8. O que você precisa fazer

1. **Revogar a chave Gemini exposta no app antigo** (Google AI Studio / Google Cloud) — urgente, independentemente do lançamento.
2. Restringir as chaves de API do Firebase ao pacote e SHA-1 no Google Cloud.
3. Configurar as credenciais no **painel → Sistema → Integrações e segurança**: Mercado Pago, Google Play (conta de serviço + RTDN), Login com Google, Firebase (push), Gemini e SMTP — passo a passo em `docs/IMPLANTACAO.md`.
4. Criar no Play Console a assinatura `sancta_plus` com os planos `mensal`, `semestral`, `anual` (+ oferta de 7 dias grátis).
5. Copiar a keystore atual para o build (`app/android/key.properties`) e publicar primeiro em **teste interno**.
6. **Licença do texto bíblico:** a tradução Ave-Maria é protegida; para um produto pago é preciso autorização da Editora Ave-Maria (ou trocar por outra versão licenciada) — o sistema aceita outras versões.
7. Revisar com um advogado os Termos e a Política de Privacidade (modelos iniciais no painel).
8. Substituir os depoimentos de exemplo (inativos) por depoimentos reais e cadastrar áudios das orações guiadas (o grande diferencial do Hallow).

## 9. Limitações conhecidas e próximos passos sugeridos

- **iOS:** o código é multiplataforma, mas a publicação na App Store exige Mac, conta Apple Developer e integração do StoreKit no servidor (hoje só Google Play está validado no servidor).
- **Lembrete diário de oração:** as preferências já são salvas; o envio programado individual (por horário de cada usuário) é um próximo passo (job agendado por fuso/horário).
- **Áudio em segundo plano** (continuar tocando com a tela desligada) pode ser adicionado com `just_audio_background`.
## 10. Compilação do APK

| Build | Resultado |
|---|---|
| Debug (`flutter build apk --debug`) | ✅ Gerado — pacote `com.maria.app_igreja`, versão **3.0.0 (8)**, targetSdk 36, nome **Sancta** |
| Release com R8 + ofuscação (`--split-per-abi --obfuscate`) | ✅ Gerado — **18,8 MB** (armeabi-v7a), **21,1 MB** (arm64-v8a), **22,5 MB** (x86_64) |
| Permissões no APK final | Internet, localização (igrejas próximas), notificações e cobrança do Google Play. As permissões de biometria herdadas de uma dependência foram removidas. |

Os APKs de release gerados aqui foram assinados com a **chave de depuração** (não há `key.properties` neste computador) e servem apenas para validar o build. Para publicar, gere o **App Bundle** com a keystore oficial (ver `docs/IMPLANTACAO.md`).

**Observação do ambiente:** neste computador o Gradle falhava com *"Unable to establish loopback connection"* (problema conhecido do Java no Windows quando a pasta do usuário contém espaço). Se acontecer, defina esta variável antes do build (no PowerShell):

```powershell
$env:JAVA_TOOL_OPTIONS = "-Djdk.net.unixdomain.tmpdir=C:/Users/Public"
```

---

## 11. Teste ponta a ponta no emulador Android (03/10/2026)

Ambiente: emulador Android 16 (API 36), APK de depuração apontando para o backend local (`http://10.0.2.2:8000`), painel aberto no navegador.

| Fluxo | Resultado |
|---|---|
| Login no app com conta de teste | ✅ Sessão salva no armazenamento seguro; pop-up de boas-vindas cadastrado no painel exibido |
| **Criar oração no painel → ver no app** | ✅ "Oração de teste do painel" (categoria Orações essenciais, destaque na tela inicial) apareceu na aba **Rezar**, no carrossel da tela **Hoje** e abriu com título, introdução e texto |
| **Sugerir igreja pelo app** | ✅ Formulário com localização do GPS enviado (HTTP 201); entrou na fila "Sugestões e correções" do painel como *Pendente*, com as coordenadas do aparelho |
| **Aprovar no painel → ver no mapa** | ✅ Após "Aprovar e publicar", a igreja apareceu na busca e como pino no mapa (Bonfim, Salvador), com a distância até o usuário |

**Problemas encontrados no teste e corrigidos:**

1. **Acentos desalinhados nos títulos e na leitura** ("você" com o circunflexo fora do lugar). O motor de texto do Flutter posiciona errado os acentos da fonte Cormorant Garamond (testado com o arquivo baixado pelo app e com o oficial do Google Fonts). A fonte de títulos e leitura passou a ser **EB Garamond** (mesma família Garamond), no app e no site. Acentuação conferida na Bíblia, na liturgia e nas orações.
2. **Pop-up "uma vez" reaparecendo a cada abertura do app.** Cada visualização incrementava o contador com `increment()` do Eloquent, que também altera `updated_at`; o app entendia que o aviso tinha sido editado no painel. Agora os contadores são atualizados sem mexer em `updated_at` (novo teste `AnnouncementTest` cobre o caso).
3. **"Usar minha localização" podia travar para sempre** sem sinal de GPS. Agora há limite de 15 s, uso da última posição conhecida, indicador de carregamento e mensagens claras (helper `core/utils/location.dart`, usado também no botão "perto de mim" do mapa).
4. **Distância "0.0 km"** → agora "350 m" abaixo de 1 km e "1,2 km" (vírgula) acima.
5. Painel: o botão do diálogo de aprovação dizia apenas "Enviar"; agora "Aprovar e publicar" / "Recusar sugestão".
6. Código Dart formatado de forma padronizada (`dart format`, largura 160 definida em `analysis_options.yaml`).

**Observação para desenvolvimento local:** no Windows, `php artisan serve` atende uma conexão por vez; com o painel aberto no navegador, as chamadas do emulador podem esperar até expirar. Use um servidor para o app (`--port=8000`) e outro para o navegador (`--port=8001`), ou Apache/Nginx + PHP-FPM. Em produção isso não se aplica.

---

## 12. Integrações e segurança configuráveis no painel (04/10/2026)

Antes, a aba "Integrações e segurança" das Configurações só **mostrava** o status; as credenciais ficavam no `.env` do servidor. Agora há uma página própria — **painel → Sistema → Integrações e segurança** — onde tudo é editado, testado e vale na hora, sem mexer no servidor:

| Aba | O que se configura |
|---|---|
| **Pagamentos** | Mercado Pago (Access Token, Public Key, assinatura do webhook), **liga/desliga Pix e cartão**, validade do QR Code Pix, URL de webhook para copiar · Google Play (pacote, conta de serviço em JSON, notificações em tempo real) |
| **Login e notificações** | Client ID Web do Google (o **app recebe sozinho**, sem nova versão) e outros client IDs · Firebase (projeto + conta de serviço) |
| **Inteligência artificial** | Chave do Gemini, modelo e limite diário por assinante |
| **E-mail** | SMTP (servidor, porta, segurança, usuário, senha, remetente) com **e-mail de teste** |
| **Segurança do painel** | Restrição por IP/faixa e **2FA obrigatório para a equipe**; status do que fica no servidor |

Cada seção tem um botão **Testar** (conta do Mercado Pago e modo teste/produção, credenciais Google/Firebase, chave do Gemini, envio de e-mail). O app também passou a ler do servidor os IDs das assinaturas cadastrados em **Planos**.

**Segurança aplicada**
- Página exclusiva do **super administrador**; salvar exige a **senha atual** (5 tentativas a cada 10 min).
- Segredos **criptografados** no banco (AES-256 com a `APP_KEY`), **nunca** voltam para a tela (só "termina em 1234" ou o e-mail da conta de serviço) e **nunca** entram no log de auditoria — que registra quem mudou **quais campos**.
- A restrição de IP **não aceita** uma lista que tranque quem está salvando; IPs/faixas inválidos são recusados.
- O `.env` continua valendo como padrão e como **piso de segurança**: se o servidor exige 2FA, o painel não consegue desligar.
- Ficam fora do painel, de propósito: modo debug, endereço do painel e pagamentos simulados (uma conta comprometida não consegue desligá-los). Cookies seguros agora são **automáticos** (ligados com HTTPS e sempre em produção).
- O 2FA obrigatório é decidido a cada requisição (funciona com `route:cache`); a fila relê as credenciais antes de cada job.
- Emergência: `php artisan sancta:admin-unlock` remove a restrição de IP e o 2FA obrigatório definidos no painel.

**Testes:** 14 novos testes automatizados (criptografia, segredo em branco mantém o valor, "Remover" volta ao `.env`, senha obrigatória, auditoria sem valores, anti-lockout de IP, 2FA ligado no painel passa a ser exigido, piso do `.env`, Pix/cartão desligados somem do checkout e são recusados, conta de serviço por conteúdo ou arquivo, teste do Mercado Pago, API do app sem segredos). Validado também no navegador: salvar, status "Configurado no painel", "Remover" e campos SMTP condicionais. **Total: 93 testes / 395 asserções no backend e 11 no app — todos passando.**

---

## 13. Rebrand "Portal" com equipe de agentes especialistas (out/2026)

O cliente aprovou uma nova identidade ("Opção A: Portal" — referências em `id/`) e pediu a atualização completa de app, site e painel, mais Ao vivo e Sancta+ mais atraentes, widget, notificações, abertura animada, integração amarrada e novos testes de invasão. O trabalho foi coordenado a partir do brief [`docs/design/BRIEF_REBRAND_PORTAL.md`](docs/design/BRIEF_REBRAND_PORTAL.md), com cada agente "dono" de uma área. Um snapshot do projeto foi guardado antes de começar.

| Fase | Especialista | Entregas principais |
|---|---|---|
| 1 | **Designer de marca** | Portal construído a partir do "n" da Figtree Bold; wordmark, ícones (padrão, escuro, temático Android 13), ícone de notificação, Play Store (ícone e banner), imagem de compartilhamento, favicons, céus "Aurora/Entardecer/Noite/Luz", ícones de postura; Figtree e EB Garamond embutidas (acentos validados); primitivas Flutter (`core/brand`); manual [`docs/design/MARCA.md`](docs/design/MARCA.md) e vitrine `brand/index.html` |
| 1 | **Backend** | **Ao vivo** (ID do canal, categoria, agenda, detecção automática via RSS + YouTube Data API, push "Ao vivo agora" uma vez por transmissão, página `/ao-vivo`); **Sancta+** (equivalente mensal e % de economia no servidor, paywall editável no painel: chamada, benefícios com ícone, prova social, FAQ, garantia); **Presentear Sancta+** no site (código guardado só como hash, resgate em `/resgatar`, estorno revoga) |
| 1 | **Segurança (pentest 1)** | 9 achados corrigidos com testes (SSRF por redirecionamento, envenenamento do link de senha, upload de SVG, IP do painel no Livewire, corrida de cupom, webhooks…) — [`docs/seguranca/PENTEST.md`](docs/seguranca/PENTEST.md) |
| 2 | **UI/UX do app** | Redesign completo: temas Claro/Escuro/Sépia, Figtree sem download em tempo de execução, 5 abas **Hoje · Orar · Bíblia · Igrejas · Você** (rotas antigas redirecionadas), tela Hoje fiel ao mockup, Ao vivo com "Me avise", paywall novo (benefícios, planos com economia, linha do tempo do teste grátis, depoimentos, FAQ), componentes coesos, acessibilidade |
| 2 | **UI/UX do site e painel** | Design system novo (`sancta.css`), todas as páginas (início com celular e widget em HTML/CSS, /planos, presentear/resgatar, checkout, conta, Bíblia, igrejas, ao vivo, erros), e-mails com a marca e em português, painel **Sancta Gestão** (cores, Figtree local, logos), CSP sem fontes externas |
| 2 | **Motion** | Splash nativo = quadro 0 da abertura; abertura de ~1,5 s (a porta acende, a cruz brilha, o Portal vira o "n" e o nome se completa), sem atrasar o app; transições, esqueletos, selo AO VIVO, ícone da aba Hoje, celebração "Amém"; [`docs/design/MOTION.md`](docs/design/MOTION.md) |
| 2 | **Plataforma Android** | Widget **Oração do dia** (4×2 e 2×2), canais *Avisos e novidades / Ao vivo / Lembretes de oração*, push em primeiro plano, **lembretes diários** (manhã/noite) locais, aviso 2 dias antes do fim do teste grátis, build com desugaring |
| 3 | **QA de integração** | Contrato API↔app (50 endpoints, 84 respostas reais validadas pelos modelos do app), 9 fluxos de pagamento ponta a ponta, oração do período configurável no painel, escala de texto até 2×, edge-to-edge, E2E no emulador em debug e **release**; **9 bugs de integração corrigidos** — [`docs/qa/RELATORIO_QA.md`](docs/qa/RELATORIO_QA.md) |
| 3 | **Segurança (pentest 2)** | 10 achados no código novo corrigidos (permissão nas chaves "Ativo" do painel, limite de cupom por pessoa, código de presente só na sessão da compra, imagem de push com download limitado, release sem assinatura de depuração, `no-store` na API…) |

**Resultado final verificado:** backend **244 testes / 5.173 asserções** verdes, Pint e `composer audit` limpos; app `flutter analyze` sem problemas e **302 testes** verdes; APK de debug e release gerados e testados no emulador.

**Decisões tomadas (podem ser revistas):** 5 abas (o mockup tinha 4; Igrejas foi mantida); "Presentear" só no site (política do Google Play); cruz do Portal acima da linha de base, como nas imagens aprovadas; versões monocromáticas com a porta aberta; ícone "Escuro" no token Tinta; splash nativo estático (todo o movimento no Flutter); o comprador pode resgatar o próprio presente.

**Pendências que dependem do cliente:** IDs dos canais do YouTube e (opcional) chave da YouTube Data API em Integrações; SMTP real antes de abrir ao público; credenciais reais para validar FCM, Google Play Billing (tag `trial` na oferta), Mercado Pago de produção, login com Google e Gemini; ícones do iOS (exigem Mac — masters em `brand/app-icon/png/`); `php artisan migrate` no MySQL local quando ele voltar a rodar (há migrations novas); revisar o limite por pessoa dos cupons existentes (agora 1 por padrão); links `sancta://` vindos de fora do app (e-mail/site) não abrem o app — se desejado, implementar App Links verificados.

## 14. Hospedagem em VPS Oracle Cloud com Ubuntu (15/10/2026)

O cliente vai hospedar o Sancta numa VPS da Oracle (2 CPUs, 12 GB, Ubuntu). O projeto já rodava em VPS sem mudança de código; o que faltava era montar e proteger o servidor. Foi criado o repositório privado `tech-tiago/sancta` (sem segredos: `.env`, `key.properties`, `local.properties` e `google-services.json` ficam fora) e a pasta [`deploy/oracle-ubuntu/`](deploy/oracle-ubuntu/):

| Arquivo | Função |
|---|---|
| `instalar.sh` | Instalação completa e repetível: Nginx, PHP 8.3, MySQL 8, Composer (instalador com assinatura conferida), portas 80/443 no iptables da Oracle, SSH só com chave, fail2ban, atualizações automáticas, usuário `sancta`, deploy key somente leitura (chaves do GitHub obtidas por HTTPS), banco com senha sorteada, `.env` de produção com caminho do painel sorteado, migrations, dados iniciais, caches, HTTPS com Let's Encrypt, cron e verificação final |
| `atualizar.sh` → `sudo sancta-atualizar` | Publica o `main` com manutenção de poucos segundos, migrations, caches e recarga do PHP-FPM; avisa variáveis novas do `.env.example` |
| `backup.sh` | Backup diário (banco, uploads e `.env`) por 14 dias em `/var/backups/sancta` |
| `nginx-sancta.conf`, `php-fpm-sancta.conf`, `php-sancta.ini`, `mysql-sancta.cnf` | Só o `index.php` executa PHP, arquivos ocultos bloqueados, uploads de até 64 MB, OPcache, pool próprio com até 24 processos, MySQL só em `127.0.0.1` |

Os scripts que rodam como root ficam em `/usr/local/sbin`, fora da pasta do código (que pertence ao usuário do PHP), para que uma invasão do site não vire acesso de root.

**Bug encontrado e corrigido:** o painel aceita áudios de até 50 MB, mas o Livewire barrava qualquer envio acima de 12 MB antes da validação do campo. Criado `backend/config/livewire.php` (limite de 50 MB, 10 minutos para o envio) e o teste `UploadLimitTest`, que falha se algum campo do painel passar a aceitar mais do que o Livewire.

**Verificado:** caminho de produção simulado numa cópia limpa do backend (Composer sem pacotes de desenvolvimento, `.env` de produção, migrations, dados iniciais sem Faker, `optimize`, `filament:optimize`, agendador): site, API e painel com 200; `/painel` antigo e `/.env` com 404; domínio estranho com 400. Backend com **245 testes / 5.177 asserções** verdes e Pint limpo; sintaxe dos scripts conferida com `bash -n`.
