# Implantação do Sancta — servidor, integrações e Play Store

Este guia leva o Sancta do ambiente local para produção. Siga na ordem.

---

## 1. Servidor (site + painel + API)

**Requisitos:** PHP 8.2+ (extensões: pdo_mysql, mbstring, openssl, intl, gd, curl, fileinfo, sodium), MySQL 8 (InnoDB, utf8mb4), HTTPS com certificado válido, acesso a cron. Funciona em VPS ou hospedagem compartilhada com SSH (ex.: Hostinger, Locaweb, DigitalOcean + Forge).

1. Envie a pasta `backend/` e aponte o **document root** do domínio para `backend/public` (nunca para a raiz do projeto).
2. Instale dependências sem pacotes de desenvolvimento:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Crie o `.env` a partir do `.env.example`. No `.env` ficam **só** os itens de infraestrutura — as credenciais das integrações são configuradas depois, no painel (seção 2):

   | Variável | Produção |
   |---|---|
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` (nunca exponha erros detalhados) |
   | `APP_URL` | `https://sancta.app.br` (seu domínio). Com `https://` os cookies seguros ligam sozinhos |
   | `SESSION_DOMAIN` | `.sancta.app.br` |
   | `DB_*` | banco MySQL |
   | `SANCTA_ADMIN_PATH` | um caminho difícil de adivinhar, ex.: `gestao-7f3k9` |
   | `SANCTA_FAKE_PAYMENTS` | `false` (o simulado já é ignorado em produção) |
   | `TRUSTED_PROXIES` | IPs do proxy/Cloudflare, se usar (necessário para a restrição de IP funcionar) |
   | `LOG_LEVEL` | `warning` |

4. Gere a chave, rode as migrations e os dados iniciais:
   ```bash
   php artisan key:generate
   php artisan migrate --force --seed
   php artisan storage:link
   ```
   > Em produção o seeder **não** cria dados de demonstração. Se `SANCTA_ADMIN_PASSWORD` estiver vazio, a senha do super administrador é gerada e exibida uma única vez — anote e troque no primeiro acesso.

   > **Guarde a `APP_KEY` com o mesmo cuidado do banco:** as credenciais salvas no painel são criptografadas com ela. Se precisar trocá-la, mantenha a antiga em `APP_PREVIOUS_KEYS` até salvar as credenciais de novo no painel.
5. Otimize:
   ```bash
   php artisan optimize
   php artisan filament:optimize
   ```
6. **Cron** (uma linha, a cada minuto):
   ```
   * * * * * cd /caminho/backend && php artisan schedule:run >> /dev/null 2>&1
   ```
   Ele busca notícias (15 min), importa a liturgia (diariamente), expira assinaturas e envia lembretes (de hora em hora), envia push agendados (cada minuto) e faz limpezas.
7. Permissões: `storage/` e `bootstrap/cache/` graváveis pelo servidor web; `.env` com permissão 600.
8. Backups diários do MySQL e da pasta `storage/app` (uploads).

### Primeiro acesso ao painel
- Entre em `https://seudominio/SANCTA_ADMIN_PATH` com o super administrador.
- Em **Perfil** ative a autenticação em dois fatores (app autenticador) e guarde os códigos de recuperação.
- Em **Sistema → Configurações** revise nome, links, versão mínima do app e recursos.
- Em **Sistema → Integrações e segurança** configure pagamentos, login com Google, push, IA e e-mail (seção 2).
- Em **Usuários → Novo membro da equipe** crie contas para editores/moderadores (cada pessoa com sua conta — nunca compartilhe login).

---

## 2. Integrações — painel → Sistema → Integrações e segurança

Tudo é configurado no painel, **sem editar arquivos nem reiniciar o servidor**. Só o super administrador acessa a página; senhas, tokens e arquivos de conta de serviço ficam **criptografados** no banco, nunca voltam para a tela (aparece só o final ou o e-mail da conta) e salvar exige a sua senha. Cada seção tem um botão **Testar**. O log de auditoria registra quem alterou quais campos — nunca os valores.

> Os campos equivalentes do `.env` (`MERCADOPAGO_*`, `GOOGLE_*`, `FCM_*`, `GEMINI_*`, `MAIL_*`, `SANCTA_ADMIN_IPS`, `SANCTA_ADMIN_REQUIRE_MFA`) continuam funcionando como **padrão**: o que for salvo no painel tem prioridade, e o botão **Remover** de cada credencial volta ao valor do servidor.

### Aba Pagamentos — Mercado Pago (Pix e cartão no site)
1. Crie a aplicação em <https://www.mercadopago.com.br/developers>.
2. Cole o **Access Token** e a **Public Key**. Comece com as credenciais de **teste** (`TEST-…`), use **Testar** e depois troque pelas de **produção** (`APP_USR-…`).
3. Em **Webhooks** do Mercado Pago, cadastre a URL mostrada na página (`https://seudominio/api/webhooks/mercadopago`), evento **Pagamentos**, e cole a **assinatura secreta** no painel.
4. Ligue/desligue **Pix** e **cartão** e ajuste a validade do QR Code Pix.

O valor cobrado é sempre calculado no servidor; o webhook só é aceito com assinatura válida e o status é reconfirmado na API do Mercado Pago antes de liberar acesso.

### Aba Pagamentos — Google Play (assinaturas no app)
1. No Play Console: **Monetizar → Produtos → Assinaturas** → crie o produto `sancta_plus` com os planos-base `mensal`, `semestral` e `anual` (IDs iguais aos cadastrados em **Assinaturas → Planos** no painel — o app lê de lá). No anual, crie uma oferta de **7 dias grátis** e dê a ela a **tag `trial`** (Play Console → oferta → Tags): com ela (ou com a fase `freeTrial` informada pela API do Google) o servidor marca a assinatura como "Período de teste" e o app agenda o aviso "seu teste termina em 2 dias".
2. Em Google Cloud, crie uma **conta de serviço**, gere a chave **JSON** e envie o arquivo no painel. Depois de salvo, apague o arquivo do seu computador.
3. No Play Console → **Usuários e permissões**, convide o e-mail da conta de serviço com permissão de **ver dados financeiros e gerenciar pedidos/assinaturas**. Use **Testar**.
4. **Notificações em tempo real (RTDN):** crie um tópico Pub/Sub e uma assinatura *push* para a URL mostrada no painel (`https://seudominio/api/webhooks/google-play`) com **autenticação habilitada**. No painel, preencha a **audiência** (normalmente a própria URL) e o **e-mail da conta** usada no push. Depois, em Play Console → Monetização → configurar RTDN, informe o tópico.

> **Política do Google Play:** conteúdo digital vendido *dentro do app* deve usar o Google Play Billing. Por isso o app usa somente o Play Billing; Pix e cartão ficam no **site** (o app não exibe links de pagamento externo). Assinaturas de ambos os canais valem na mesma conta.

### Aba Login e notificações
**Entrar com Google**
1. Em Google Cloud → APIs e serviços → Credenciais, crie clientes OAuth:
   - **Android**: pacote `com.maria.app_igreja` + SHA-1 da chave de assinatura (Play Console → Integridade do app) **e** da chave de upload.
   - **Web**: é o que o app usa para pedir o login.
2. No painel, cole o **Client ID Web** (o app recebe esse valor automaticamente, sem nova versão) e, em "Outros client IDs", o do Android.

**Notificações push (Firebase)**
1. Use o projeto Firebase já existente (`app-paroquia-775b6`) — o `google-services.json` do app atual já foi copiado para `app/android/app/`.
2. Firebase → Configurações do projeto → Contas de serviço → **Gerar nova chave privada**; envie o JSON no painel e informe o ID do projeto. Use **Testar**.

### Aba Inteligência artificial
1. **Revogue imediatamente** a chave Gemini que estava dentro do app antigo (`biblia_page.dart`) — ela está exposta em todos os APKs publicados.
2. Gere uma nova chave em <https://aistudio.google.com> (restrinja ao IP do servidor), cole no painel e use **Testar**. A chave fica só no servidor; o app nunca a recebe. Ajuste o modelo e o limite diário por assinante.

### Aba E-mail
Escolha **SMTP** e preencha servidor, porta (587 com STARTTLS ou 465 com SSL), usuário, senha e remetente. Use **Enviar e-mail de teste para mim**. Sem SMTP, confirmação de cadastro e redefinição de senha não chegam aos usuários.

### Aba Segurança do painel
- **Permitir o painel só destes IPs**: opcional; aceita faixas (`200.10.0.0/24`). O painel não deixa salvar uma lista que não inclua o seu IP atual.
- **Exigir 2FA para toda a equipe**: recomendado. Quem ainda não ativou é levado à configuração no próximo acesso.
- Itens que **não** mudam pelo painel (por segurança): modo debug, cookies seguros, endereço do painel e pagamentos simulados.

**Emergência — alguém ficou trancado fora** (IP mudou, celular do 2FA perdido): no servidor, rode
```bash
php artisan sancta:admin-unlock
```
Isso remove a restrição de IP e o 2FA obrigatório definidos no painel (o que estiver no `.env` continua valendo).

> Se usar fila de jobs (`php artisan queue:work`), não é preciso reiniciá-la ao mudar credenciais: cada job relê o que foi salvo no painel.

### Mapas
O padrão usa os tiles do OpenStreetMap (sem chave), que têm política de uso justo. Com o crescimento do app, contrate um provedor (MapTiler, Stadia Maps, Mapbox) e troque `SANCTA_MAP_TILES_LIGHT`/`DARK` — o app recebe as URLs via `/api/v1/config`, sem nova versão.

---

## 3. Publicar o app como atualização na Play Store

O novo app usa o **mesmo `applicationId` (`com.maria.app_igreja`)**, então ele substitui o app atual para todos os usuários.

1. Copie `app/android/key.properties.example` para `app/android/key.properties` e aponte para a **mesma keystore de upload** usada hoje (`upload-keystore.jks`, que está na pasta do projeto antigo). Nunca versione esses arquivos e mantenha cópia segura offline.
   **Sem `key.properties`, o build de release falha de propósito** (`flutter build apk/appbundle --release`, `assembleRelease`, `bundleRelease`): assim um pacote assinado com a chave de depuração nunca é gerado por engano. Somente para testar um release localmente (nunca para publicar), libere a assinatura de depuração explicitamente:
   ```bash
   SANCTA_ALLOW_DEBUG_SIGNING=true flutter build apk --release      # Git Bash / Linux / macOS
   # PowerShell: $env:SANCTA_ALLOW_DEBUG_SIGNING="true"; flutter build apk --release
   ```
   (Também vale `-PSANCTA_ALLOW_DEBUG_SIGNING=true` ao chamar o Gradle direto.) Não defina essa variável na máquina ou no CI que gera o pacote da Play Store.
2. A versão já foi definida como `3.0.0+8` (o app atual é `2.1.2+7`). Cada nova publicação deve aumentar o número após o `+`.
3. Gere o pacote (o client ID do Google e os IDs das assinaturas vêm do painel; não precisam ir no build):
   ```bash
   flutter build appbundle --release --obfuscate --split-debug-info=build/symbols --dart-define=API_BASE_URL=https://sancta.app.br
   ```
   Guarde a pasta `build/symbols` (necessária para ler relatórios de falha).
4. Na ficha da loja, atualize **nome** ("Sancta: Bíblia, Liturgia e Orações"), ícone (`brand/png/play-store-icon-512.png`), banner (`brand/png/play-feature-graphic-1024x500.png`), capturas de tela e descrição.
5. **Segurança de dados** (formulário do Play Console): declare coleta de nome, e-mail, localização aproximada (opcional, para igrejas próximas), histórico de compras e identificadores do dispositivo (push); criptografia em trânsito; exclusão de conta disponível pelo app e pelo site (`/excluir-conta`).
6. Publique primeiro em **teste interno**, valide login, compra (contas de teste de licença), push e mapa; depois **produção com lançamento gradual** (10% → 50% → 100%).
7. Usuários antigos: o login com Google cria/recupera a conta pelo mesmo e-mail. Para presentear benfeitores do app antigo, exporte os dados do Firestore em JSON e rode:
   ```bash
   php artisan sancta:import-legacy-users caminho/usuarios.json
   ```
   (R$ 5 → 1 mês · R$ 20 → 3 meses · R$ 50 → 6 meses · R$ 100+ → 12 meses de Sancta+ de cortesia).
8. No painel, ajuste **Configurações → Versão mínima** quando quiser obrigar a atualização.

---

## 4. Checklist de segurança antes de abrir ao público

- [ ] `APP_DEBUG=false` e HTTPS ativo (`APP_URL` com `https://`)
- [ ] `APP_KEY` guardada em local seguro (as credenciais do painel dependem dela)
- [ ] Caminho do painel alterado; **2FA obrigatório** e (se possível) restrição de IP em Integrações e segurança
- [ ] Chave Gemini antiga revogada; nova chave cadastrada só no painel
- [ ] Chaves de API do Firebase restritas ao pacote/SHA-1 no Google Cloud
- [ ] Arquivos JSON das contas de serviço apagados do computador depois de enviados ao painel
- [ ] Webhooks do Mercado Pago e do Pub/Sub com assinatura/autenticação configuradas (botões **Testar** verdes)
- [ ] E-mail de teste recebido (SMTP configurado) — com o e-mail em modo "registro" (log), códigos de presente **não** são enviados (só aparecem uma vez na página do pagamento) e "gerar novo código" fica indisponível
- [ ] Cupons revisados: cada cupom vale **uma vez por pessoa** por padrão (campo "Limite por pessoa"; vazio = sem limite)
- [ ] Pacote da Play Store gerado com `key.properties` (sem `SANCTA_ALLOW_DEBUG_SIGNING`)
- [ ] Backups automáticos do banco e de `storage/app`
- [ ] Senha do super administrador trocada; cada membro da equipe com conta própria
- [ ] Textos de Termos e Privacidade revisados por um advogado (modelos iniciais no painel → Site → Páginas)
- [ ] Licença do texto bíblico resolvida (ver SOBRE_O_PROJETO.md → Riscos)
