# API do Sancta — v1

Base: `https://SEU_DOMINIO/api/v1` · JSON em UTF-8 · erros sempre em JSON com `message` (e `errors` por campo em validações 422).

**Autenticação:** `Authorization: Bearer <token>` (token Sanctum por aparelho, com validade configurável — padrão 60 dias). Rotas públicas aceitam o token opcionalmente: com ele, o conteúdo Sancta+ é liberado para assinantes.

**Limites de requisição (HTTP 429):** geral 120/min · login/cadastro 10/min por IP e 5 tentativas por e-mail+IP · recuperação de senha 3/min · busca 30/min · envios (igrejas/intenções) 10/hora · IA 10/min e 30/dia · uploads 20/hora.

**Códigos especiais:** `401` sessão expirada · `402` conteúdo exclusivo Sancta+ (`paywall: true`) · `403` conta suspensa.

**Mapas vazios são `{}`, nunca `[]`:** `prayers` de `/liturgy`, `contacts` e `schedules` de `/churches/{uuid}` e `notification_preferences` do usuário (o PHP serializaria um array associativo vazio como lista e o app — que lê esses campos como objeto — quebraria a tela).

**Contrato testado:** `backend/tests/Feature/Contract/ApiFixturesTest.php` chama todos os endpoints usados pelo app (visitante, gratuito e assinante) e grava as respostas reais em `app/test/fixtures/api/*.json`; `app/test/contract_test.dart` faz o parse delas com os modelos do app e falha se o app passar a chamar um endpoint sem resposta gravada. Regerar (pasta `backend/`): `SANCTA_WRITE_FIXTURES=1 php artisan test --filter=ApiFixturesTest`.

## Públicas
| Método | Rota | Descrição |
|---|---|---|
| GET | `/config` | Configuração remota: versão mínima, manutenção, recursos, **paywall Sancta+** (ver [Paywall](#paywall-sancta--bloco-premium-de-config)), mapas, links legais, id ofuscado para compras |
| GET | `/home` | Tela "Hoje" completa em uma chamada (saudação, versículo, liturgia, santo, jornadas, orações, **`period_prayers`** (oração da manhã/tarde/noite), lives, **`live_now`**, eventos, notícias) |
| GET | `/liturgy?date=AAAA-MM-DD` | Liturgia do dia (leituras, orações; reflexão só para assinantes) |
| GET | `/bible/books` | Livros da Bíblia (cacheável) |
| GET | `/bible/books/{livro}/chapters/{n}` | Capítulo com versículos (+ destaques/notas do usuário, se autenticado) |
| GET | `/bible/search?q=` | Busca full-text (mín. 3 letras) |
| GET | `/categories` · `/prayers?category=&q=` · `/prayers/{slug}` | Orações (conteúdo premium bloqueado no servidor) |
| GET | `/journeys` · `/journeys/{slug}` | Jornadas, novenas e planos de leitura |
| GET | `/churches?lat=&lng=&radius=&q=&bbox=&state=&mass_today=` · `/churches/{uuid}` | Igrejas (proximidade, área visível, busca) e detalhes com horários |
| GET | `/news` · `/news/{id}` | Notícias |
| GET | `/live-channels` | Canais ao vivo com status, agenda e próximo horário (ver [Ao vivo](#ao-vivo)) |
| GET | `/plans` | Planos do app com equivalente mensal e % de economia (ver [Planos](#planos-sancta)) |
| GET | `/events` · `/saints/today` · `/pages/{slug}` · `/faqs` | Conteúdos diversos |
| GET | `/intentions` · `/announcements` | Intenções aprovadas · avisos/pop-ups ativos para o público do visitante |
| POST | `/announcements/{id}/interactions` | `{action: view|click|dismiss}` (métricas) |

## Autenticação
| Método | Rota | Corpo |
|---|---|---|
| POST | `/auth/register` | `name, email, password, device_name, accept_terms, marketing_opt_in?` |
| POST | `/auth/login` | `email, password, device_name` |
| POST | `/auth/google` | `id_token, device_name` (validado no servidor com as chaves do Google) |
| POST | `/auth/forgot-password` | `email` (resposta idêntica exista ou não a conta) |
| POST | `/auth/logout` · `/auth/logout-all` | — |

## Autenticadas
| Método | Rota | Descrição |
|---|---|---|
| GET/PATCH/DELETE | `/me` | Perfil · atualizar (`name, theme, marketing_opt_in, notification_preferences`) · excluir conta (`confirmation: "EXCLUIR", password`). `notification_preferences` substitui o objeto inteiro (envie todas as chaves): `daily_reminder` + `reminder_time` (lembrete da manhã, "HH:MM"), `evening_reminder` + `evening_reminder_time` (lembrete da noite), `liturgy`, `news`, `churches` |
| PUT | `/me/password` | `current_password, password, password_confirmation` (encerra as outras sessões) |
| POST | `/me/avatar` | multipart `avatar` (jpg/png/webp, até 4 MB) |
| GET | `/me/stats` · `/me/subscription` | Sequência de dias, minutos de oração · assinatura e pagamentos |
| POST/DELETE | `/me/devices` | Registrar/remover token de push |
| GET/PUT | `/me/bible/progress` | Continuar leitura |
| GET/PUT | `/me/bible/annotations` | Listar (`type=bookmarks|notes|highlights`) · gravar (`verse_ids[], highlight?, note?, bookmarked?`) |
| POST | `/bible/explain` | `verse_ids[]` — explicação por IA (Sancta+) |
| GET | `/me/favorite-prayers` · `/me/favorite-churches` | Favoritos |
| POST | `/prayers/{slug}/favorite` · `/churches/{uuid}/favorite` | Alternar favorito |
| POST | `/journeys/{slug}/steps/{n}/complete` | Concluir dia da jornada |
| POST | `/me/prayer-sessions` | `content_type, content_id?, seconds` — alimenta a sequência de dias |
| POST | `/church-submissions` | Sugestão de igreja ou correção (`church_uuid` + campos) — vai para moderação |
| POST | `/intentions` · `/intentions/{uuid}/pray` | Enviar intenção (moderada) · "Rezei por esta intenção" |
| POST | `/billing/google-play/verify` | `product_id, purchase_token` — validação no Google Play e liberação do acesso |

## Webhooks (servidor ↔ provedores)
| Rota | Proteção |
|---|---|
| `POST /api/webhooks/mercadopago` | Assinatura HMAC-SHA256 (`x-signature`), janela anti-replay de 15 min, idempotência, reconsulta do pagamento na API do MP |
| `POST /api/webhooks/google-play` | Token OIDC do Google (Pub/Sub push) com audiência e conta de serviço conferidas, idempotência por `messageId` |

---

## Ao vivo

### `GET /live-channels` (público)

Lista os canais **ativos**, já ordenados: **ao vivo** → **no horário da agenda** → ordem do painel (`sort_order`).
Datas em ISO-8601 no fuso **America/Sao_Paulo** (`-03:00`). Resposta = array (sem envelope `data`).

```json
[
  {
    "id": 3,
    "name": "TV Ao Vivo",
    "description": "Missas e orações",
    "category": "missa",
    "category_label": "Missa",
    "cover_url": "https://SEU_DOMINIO/storage/live/capas/abc.jpg",
    "logo_url": "https://SEU_DOMINIO/storage/live/logo.png",
    "youtube_url": "https://www.youtube.com/channel/UCabcdefghijklmnopqrstuv",
    "watch_url": "https://www.youtube.com/watch?v=LIVEvideo01",
    "is_live": true,
    "live": {
      "video_id": "LIVEvideo01",
      "title": "Santa Missa",
      "started_at": "2026-10-04T09:40:00-03:00",
      "thumbnail_url": "https://i.ytimg.com/vi/LIVEvideo01/hqdefault.jpg"
    },
    "upcoming": null,
    "on_schedule_now": false,
    "current_schedule_title": null,
    "schedule": [
      { "weekday": null, "time": "07:00", "duration_minutes": 60, "title": "Santa Missa" },
      { "weekday": 0, "time": "18:00", "duration_minutes": 90, "title": "Missa de domingo" }
    ],
    "next_at": "2026-10-05T07:00:00-03:00",
    "topic": "sancta_live_3",

    "type": "live",
    "url": "https://www.youtube.com/watch?v=LIVEvideo01",
    "video_id": "LIVEvideo01",
    "fallback_url": null,
    "thumbnail_url": "https://i.ytimg.com/vi/LIVEvideo01/hqdefault.jpg"
  }
]
```

| Campo | Significado |
|---|---|
| `category` | `missa` · `terco` · `adoracao` · `oracao` · `canal` (`category_label` = rótulo pt-BR) |
| `cover_url` · `logo_url` | Imagens do painel (podem ser `null`) |
| `youtube_url` | Página do canal (ou o link cadastrado) |
| `watch_url` | **Abra este**: vídeo ao vivo quando houver, senão `youtube_url` |
| `is_live` / `live` | Live detectada pelo servidor (YouTube Data API). `live` = `null` quando não está ao vivo |
| `upcoming` | `{video_id, title, scheduled_at}` de uma transmissão agendada no YouTube (até 7 dias), senão `null` |
| `on_schedule_now` | `true` quando o horário atual está dentro de um item da `schedule` (independe da detecção) |
| `current_schedule_title` | Título do item da agenda em andamento (ou `null`) |
| `schedule[]` | `weekday`: 0 = domingo … 6 = sábado, `null` = todos os dias · `time` "HH:MM" (Brasília) · `duration_minutes` · `title` (pode ser `null`) |
| `next_at` | Próximo **início** da agenda ainda não começado (não inclui o que está em andamento); `null` sem agenda |
| `topic` | Tópico FCM do "Me avise": o app assina `sancta_live_<id>` |
| `type` · `url` · `video_id` · `fallback_url` · `thumbnail_url` | **Compatibilidade com o app 3.0** (`url` = `watch_url`; `thumbnail_url` = miniatura da live → capa → logo → miniatura do vídeo do link) |

**Regras do status**
- Com a chave da YouTube Data API (painel → Integrações e segurança → **Conteúdo**), o comando `sancta:live-sync` (a cada 3 min) lê o RSS público do canal e consulta `videos.list` em lotes de 50 vídeos (1 unidade de cota por lote). `live` = `actualStartTime` sem `actualEndTime`; `upcoming` = `scheduledStartTime` futuro (tolerância de 60 min de atraso; ignora agendamentos a mais de 7 dias).
- **Sem chave**: nenhuma chamada ao YouTube; `is_live` = `false` e o app usa `on_schedule_now` ("No horário da transmissão").
- Se o servidor não conseguir verificar por mais de 20 min (falha de rede/cota), `is_live`/`upcoming` voltam a `false`/`null` sozinhos (não fica "ao vivo" preso).
- O vídeo fixo do link (`youtube_url` com `watch?v=…`, comum em TVs 24h) também é conferido, mesmo fora do RSS.

**Push "Ao vivo agora"** (uma vez por vídeo; não envia para lives iniciadas há mais de 3 h):
- tópico `sancta_live_<id>` · canal Android **`sancta_ao_vivo`** · título `Ao vivo agora: <canal>` · corpo = título da live · imagem = miniatura do vídeo
- `data`: `{"deep_link": "sancta://ao-vivo", "type": "live", "channel_id": "3", "video_id": "LIVEvideo01"}`

### `GET /home` → `live_now`

Destaque "Ao vivo agora": canais **ao vivo** ou **no horário da agenda** (mesma ordem acima, até 6). Vazio (`[]`) com a área "Ao vivo" desligada em Configurações → Recursos.

```json
"live_now": [
  {
    "id": 3, "name": "TV Ao Vivo", "category": "missa",
    "is_live": true, "on_schedule_now": false,
    "title": "Santa Missa",
    "started_at": "2026-10-04T09:40:00-03:00",
    "watch_url": "https://www.youtube.com/watch?v=LIVEvideo01",
    "thumbnail_url": "https://i.ytimg.com/vi/LIVEvideo01/hqdefault.jpg",
    "logo_url": null,
    "topic": "sancta_live_3"
  },
  {
    "id": 5, "name": "Terço Diário", "category": "terco",
    "is_live": false, "on_schedule_now": true,
    "title": "Terço da manhã", "started_at": null,
    "watch_url": "https://www.youtube.com/@terco",
    "thumbnail_url": null, "logo_url": null, "topic": "sancta_live_5"
  }
]
```

`live_channels` (já existente) continua igual e ganhou `is_live`; seu `url` passou a ser o `watch_url`.

### `GET /home` → `period_prayers` (oração do período)

A oração do cartão principal da tela **Hoje** ("Oração da manhã/da tarde/da noite") e do widget **Oração do dia**.
Sempre traz as três chaves; cada uma é uma oração no **mesmo formato dos itens de `featured_prayers`** (com `locked`
calculado para quem pede) ou `null`.

```json
"period_prayers": {
  "morning":   { "id": 14, "slug": "oracao-da-manha", "title": "Oração da manhã", "duration_minutes": 3, "locked": false, "category": { "slug": "manha-e-noite", "name": "Manhã e noite", "color": "#E8B04B" }, "...": "…" },
  "afternoon": { "id": 10, "slug": "angelus", "title": "Angelus", "...": "…" },
  "night":     null
}
```

| Período (horário local do aparelho) | Chave | Escolha |
|---|---|---|
| 05h–11h59 | `morning` | Painel → **Configurações → Aplicativo → Oração do período** (oração publicada); vazio = automático |
| 12h–17h59 | `afternoon` | idem |
| 18h–04h59 | `night` | idem |

- **Automático** (padrão): procura palavras-chave no título/slug e, se não achar, na categoria — manhã: "oração da manhã", "manhã", "laudes"…; tarde: "angelus", "tarde", "misericórdia", "terço"…; noite: "oração da noite", "noite", "completas", "exame", "dormir"… — preferindo orações **gratuitas**, depois destaques e a ordem do painel; a mesma oração não se repete em dois períodos.
- Oração escolhida que deixar de estar publicada → volta ao automático. Escolha cacheada por até 5 min.
- Oração Sancta+ escolhida no painel chega com `locked: true` para quem não assina (o app abre o paywall; o widget a ignora).
- **App:** usa `period_prayers` e, se a chave faltar ou vier `null` (servidor antigo), mantém a heurística local sobre `featured_prayers`.

Site: página pública **`/ao-vivo`** (rota `live`) com "Ao vivo agora", "Agenda de hoje" e "Canais" (links abrem o YouTube em nova aba, `rel="noopener"`).

---

## Planos Sancta+

### `GET /plans` (público) — planos ativos com `available_on_app`

Resposta = array. Valores em **centavos** (inteiros), calculados no servidor:

```json
[
  {
    "slug": "anual",
    "name": "Anual",
    "description": "O melhor valor — 7 dias grátis para experimentar.",
    "interval": "year",
    "interval_count": 1,
    "period_label": "por ano",
    "price_cents": 14990,
    "price_formatted": "R$ 149,90",
    "compare_at_price_cents": 23880,
    "compare_at_price_formatted": "R$ 238,80",
    "monthly_equivalent_cents": 1249,
    "monthly_equivalent_formatted": "R$ 12,49",
    "savings_percent": 37,
    "trial_days": 7,
    "max_installments": 12,
    "badge": "Mais popular",
    "is_featured": true,
    "features": ["Todas as orações guiadas e em áudio", "…"],
    "google_play": { "product_id": "sancta_plus", "base_plan_id": "anual" }
  }
]
```

- `monthly_equivalent_cents` = preço ÷ meses do período (arredondado ao centavo); `null` no vitalício.
- `savings_percent` = economia em relação a pagar o **plano mensal ativo do mesmo canal** (app aqui; site na página `/planos`) pelo mesmo período, **arredondada para baixo** (24,7% → 24). Se houver mais de um mensal, usa o mais barato. `null` para o próprio mensal, vitalício, quando não há mensal ou quando não há economia.
- Com `trial_days > 0`, o app pode mostrar a linha do tempo "Hoje → lembrete → cobrança".

---

## Paywall Sancta+ — bloco `premium` de `/config`

Editável em **Painel → Configurações → aba Sancta+**.

```json
"premium": {
  "name": "Sancta+",
  "headline": "Aprofunde sua vida de oração",
  "subheadline": "Orações guiadas, jornadas e reflexões para rezar todos os dias — no app e no site, com a mesma conta.",
  "benefits": ["Todas as orações guiadas, jornadas e novenas", "Reflexão diária do Evangelho e homilia"],
  "benefit_items": [
    { "icon": "headphones", "title": "Todas as orações guiadas, jornadas e novenas", "description": "Reze com calma, passo a passo, onde estiver." },
    { "icon": "sun", "title": "Reflexão diária do Evangelho e homilia", "description": "Uma meditação curta para cada dia da liturgia." }
  ],
  "social_proof": null,
  "guarantee": "Cancele quando quiser",
  "faq": [
    { "question": "Posso cancelar quando quiser?", "answer": "Sim. No app, a assinatura é gerenciada pelo Google Play…" }
  ],
  "testimonials": [
    { "author": "Maria", "content": "Rezo todos os dias com o app.", "rating": 5 }
  ]
}
```

- **Compatibilidade:** `benefits` continua sendo **lista de strings** (o app 3.0 lê assim). O novo app deve usar `benefit_items`.
- `icon` é sempre uma destas chaves (o app/site mapeia para o próprio ícone; chave desconhecida vira `star`):
  `headphones` · `flame` · `sun` · `sparkles` · `book` · `calendar` · `heart` · `cross` · `chalice` · `play` · `moon` · `users` · `shield` · `star`.
- `subheadline`, `social_proof`, `guarantee`: `null` quando vazios. `description` de um benefício pode ser `null`.
- `testimonials`: depoimentos **ativos** do painel (Site → Depoimentos), até 10, só nome exibido, texto e nota 1–5 (sem cidade ou outros dados).
- Textos chegam sem HTML.

---

## Presentear Sancta+ (somente no site — não há rotas na API)

Política do Google Play: o app **não** vende nem resgata presentes. O acesso resgatado no site aparece no app automaticamente (`/me`, `/me/subscription` e o conteúdo liberado), sem nada novo na API.

| Rota (site) | Acesso | O que faz |
|---|---|---|
| `GET /presentear` (`gift.index`) | pública | Escolher o plano do presente |
| `GET /assinar/{plano}?gift=1` → `POST /assinar/{plano}` com `gift=1` | logado | Checkout Pix/cartão (valor sempre do servidor). Pagamento fica com `is_gift = true` |
| `GET /pagamento/{uuid}` | dono | Após aprovação mostra o código **uma única vez** (`Cache-Control: no-store`) |
| `GET/POST /resgatar` (`gift.redeem`) | logado, CSRF, 5/min e 20/dia por usuário · 10/min e 50/dia por IP | Resgate; mensagem sempre genérica para código inválido/expirado/usado |
| `POST /minha-conta/presentes/{uuid}/novo-codigo` | comprador, 3/hora | Gera um novo código e envia **só por e-mail** (o anterior deixa de valer) |

- Código `SANC-XXXX-XXXX-XXXX` (Crockford base32, 60 bits de `random_bytes`); na digitação aceita minúsculas, espaços e O/I/L no lugar de 0/1.
- Banco: só `code_hash` (HMAC-SHA256 com a `APP_KEY`; `APP_PREVIOUS_KEYS` continua aceitando códigos antigos se a chave for trocada) e `last4`. Validade de 12 meses.
- Aprovação: gera o código (idempotente — um por pagamento) em vez de dar acesso ao comprador; e-mail ao comprador (`GiftCodeIssued`, enviado sem fila para o código não ir para a tabela `jobs`).
- Resgate: soma o período do plano ao acesso atual de quem resgata (como os planos do site).
- Estorno/chargeback: código disponível → `revoked`; já resgatado → o período de quem resgatou é encerrado.
- Painel: **Assinaturas → Presentes** (somente leitura, admin+, ação "Revogar" auditada; nunca mostra o código).

## Comandos agendados (novos)

| Comando | Frequência | O que faz |
|---|---|---|
| `sancta:live-sync [--force]` | a cada 3 min (`withoutOverlapping`) | Atualiza o status ao vivo e envia o push "Ao vivo agora". Não roda com a área "Ao vivo" desligada (exceto `--force`). Nunca falha por rede (log de aviso) |
| `sancta:subscriptions-maintenance` | de hora em hora | Agora também marca como `expired` os presentes não resgatados após a validade |
