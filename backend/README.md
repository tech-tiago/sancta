# Sancta — Backend (Laravel 12 · MySQL · Filament 5)

Um único projeto Laravel entrega três coisas:

1. **API REST v1** do aplicativo (`/api/v1`) — documentada em [`../docs/API.md`](../docs/API.md).
2. **Site público** (Blade + CSS próprio, temas claro/escuro): apresentação do app, Bíblia online, mapa de igrejas com sugestões/correções, planos Sancta+ com Pix e cartão (Mercado Pago), área do assinante, páginas legais.
3. **Painel administrativo** (Filament) em `/{SANCTA_ADMIN_PATH}` — gerencia absolutamente tudo do app e do site.

## Estrutura

```
app/
  Console/Commands/     rotinas agendadas (notícias, liturgia, assinaturas, push, limpeza) e importação do app antigo
  Enums/                papéis, status de assinatura/pagamento, tipos de igreja/horário, temas…
  Filament/             painel: Pages (Dashboard, Configurações), Resources (25 cadastros), Widgets (KPIs e gráficos)
  Http/
    Controllers/Api/V1  API do app
    Controllers/Web     site público e área do assinante
    Middleware/         cabeçalhos de segurança/CSP, restrição de IP do painel, bloqueio de contas suspensas
    Resources/          formato JSON da API (com bloqueio de conteúdo Sancta+ no servidor)
  Models/ (+Concerns)   Eloquent; UUID público, slugs, auditoria automática das ações da equipe
  Policies/             autorização por papel (moderador < editor < admin < super admin)
  Services/
    Billing/            checkout (Pix/cartão), Mercado Pago, Google Play Billing, webhooks, direitos de acesso
    Content/            liturgia, notícias RSS (anti-SSRF), explicação por IA
    Community/          sequência de oração, sugestões de igrejas
    Google/ Push/       verificação de tokens Google, conta de serviço, Firebase Cloud Messaging
    Account/            login, troca de senha, exclusão de conta (LGPD)
  Support/              sanitização de HTML, proteção SSRF, dinheiro
database/
  migrations/           49 tabelas (MySQL InnoDB; FULLTEXT para a busca bíblica)
  seeders/              Bíblia (73 livros), orações, jornadas, igrejas, planos, santos, páginas LGPD, demo (local)
  data/                 dados herdados do app antigo (Bíblia, orações eucarísticas, Via-Sacra, igrejas)
resources/views/        site (layouts, páginas, Bíblia, igrejas, checkout, conta, erros)
public/css/sancta.css   design system do site (sem build de Node)
tests/                  93 testes (Feature + Unit) — segurança, pagamentos, permissões, API, site
```

## Comandos úteis

| Comando | Para quê |
|---|---|
| `php artisan migrate --seed` | Cria as tabelas e os dados iniciais |
| `php artisan test` | Roda todos os testes |
| `php artisan schedule:work` | Agendador em desenvolvimento |
| `php artisan sancta:fetch-news --force` | Importa notícias agora |
| `php artisan sancta:prefetch-liturgy --days=7` | Importa a liturgia dos próximos dias |
| `php artisan sancta:subscriptions-maintenance` | Expira assinaturas/cobranças e envia lembretes |
| `php artisan sancta:send-scheduled-push` | Envia notificações agendadas |
| `php artisan sancta:import-legacy-users arquivo.json` | Migra usuários e benfeitores do app antigo |

Variáveis de ambiente e implantação: [`../docs/IMPLANTACAO.md`](../docs/IMPLANTACAO.md).
