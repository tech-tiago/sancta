# Sancta — Ora · Lê · Vive

Plataforma católica completa, inspirada no Hallow, que substitui o app **"Paróquia Nossa Sra. da Saúde"** (`com.maria.app_igreja`) por um produto novo, profissional e 100% gerenciado por painel administrativo.

| Pasta | O que é |
|---|---|
| [`app/`](app/) | Aplicativo **Flutter** (Android/iOS) — mesmo `applicationId` do app publicado, chega como **atualização** na Play Store. |
| [`backend/`](backend/) | **Laravel 12 + MySQL**: API do app, **site público** (apresentação, Bíblia online, mapa de igrejas, planos com Pix/cartão, área do assinante) e **painel administrativo** (Filament 5). |
| [`brand/`](brand/) | Identidade visual: marca em SVG/PNG, ícones Android (todas as densidades + adaptativo + notificação), ícone e banner da Play Store, favicon, imagem social. |
| [`docs/`](docs/) | Guias técnicos: [implantação e Play Store](docs/IMPLANTACAO.md) e [referência da API](docs/API.md). |
| [`deploy/`](deploy/) | Instalador e atualização para VPS Ubuntu (Oracle Cloud): Nginx, PHP, MySQL, HTTPS, backup e proteção do servidor — passo a passo em [IMPLANTACAO.md](docs/IMPLANTACAO.md#vps-oracle-cloud-com-ubuntu--instalação-automática). |
| [`RELATORIO_DO_QUE_FOI_FEITO.md`](RELATORIO_DO_QUE_FOI_FEITO.md) | Relatório completo de tudo que foi analisado, construído e testado. |
| [`SOBRE_O_PROJETO.md`](SOBRE_O_PROJETO.md) | Documento do produto: visão, funcionalidades, modelo de assinatura, arquitetura e roadmap. |

---

## Início rápido (ambiente local)

### 1. Backend (site + painel + API)

Requisitos: PHP 8.2+, Composer, MySQL 8 (InnoDB, utf8mb4).

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Crie o banco `sancta` no MySQL, ajuste `DB_*` no `.env` e então:

```bash
php artisan migrate --seed
```

```bash
php artisan storage:link
```

```bash
php artisan serve
```

- Site: <http://localhost:8000>
- Painel: <http://localhost:8000/painel> (o caminho é configurável em `SANCTA_ADMIN_PATH`)
- O seeder cria o super administrador definido em `SANCTA_ADMIN_EMAIL` / `SANCTA_ADMIN_PASSWORD` (se a senha ficar vazia, uma senha forte é gerada e exibida uma única vez no terminal).
- Agendador (notícias, liturgia, assinaturas, push agendado): `php artisan schedule:work`

Testes:

```bash
php artisan test
```

### 2. Aplicativo Flutter

```bash
cd app
flutter pub get
```

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

(`10.0.2.2` é o "localhost" do computador visto pelo emulador Android. Em produção use `https://`.)

No Windows, `php artisan serve` atende uma conexão por vez: se o painel estiver aberto no navegador, o app pode ficar esperando. Abra o painel em um segundo servidor (`php artisan serve --port=8001`) e deixe a porta 8000 só para o app.

Testes e análise estática:

```bash
flutter test
```

```bash
flutter analyze
```

Build de produção (veja [docs/IMPLANTACAO.md](docs/IMPLANTACAO.md)):

```bash
flutter build appbundle --release --obfuscate --split-debug-info=build/symbols --dart-define=API_BASE_URL=https://sancta.app.br
```

---

## Situação dos testes (14/10/2026 — após o rebrand "Portal")

| Verificação | Resultado |
|---|---|
| Backend — PHPUnit | **245 testes, 5.177 asserções — todos passando** (inclui contrato API↔app, fluxos de pagamento, pentest e limite de upload) |
| Backend — `composer audit` | Nenhuma vulnerabilidade conhecida nas dependências |
| Backend — Pint (padrão de código) | Aplicado |
| App — `flutter analyze` | **Nenhum problema** |
| App — `flutter test` | **302 testes — todos passando** (contrato, acessibilidade até 2×, navegação, goldens, motion, notificações) |
| Painel — 43 telas abertas no navegador | Todas respondendo 200 |
| Migrations + seeders no MySQL 8.4 | OK (73 livros, 35.450 versículos, 33 orações, 3 jornadas, 5 igrejas…) |
| App — build Android | ✅ Debug e release (R8 + ofuscação): 18,8–22,5 MB por arquitetura; pacote `com.maria.app_igreja` 3.0.0 (8) |
| App no emulador (Android 16) — ponta a ponta | ✅ Oração criada no painel aparece no app; sugestão de igreja enviada pelo app, aprovada no painel e exibida no mapa (detalhes no relatório, seção 11) |
| Rebrand "Portal" — QA completo no emulador (debug e release) | ✅ Todas as telas, widget, lembretes, painel ↔ app; 53 capturas em [`docs/qa/telas/`](docs/qa/telas/) e relatório em [`docs/qa/RELATORIO_QA.md`](docs/qa/RELATORIO_QA.md) |
