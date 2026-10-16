# Implantação do Sancta — servidor, integrações e Play Store

Este guia leva o Sancta do ambiente local para produção. Siga na ordem.

---

## 1. Servidor (site + painel + API)

**Requisitos:** PHP 8.2+ (extensões: pdo_mysql, mbstring, openssl, intl, gd, curl, fileinfo, sodium), MySQL 8 (InnoDB, utf8mb4), HTTPS com certificado válido, acesso a cron. Funciona em VPS ou hospedagem compartilhada com SSH (ex.: Hostinger, Locaweb, DigitalOcean + Forge).

> **VPS da Oracle com CloudPanel e Cloudflare?** Os passos abaixo estão automatizados: veja [VPS Oracle Cloud com CloudPanel e Cloudflare](#vps-oracle-cloud-com-cloudpanel-e-cloudflare).

Uploads: o painel aceita áudios de até 50 MB. Na hospedagem, o servidor web e o PHP precisam aceitar pelo menos isso (`client_max_body_size` no Nginx, `upload_max_filesize` e `post_max_size` no PHP; o modelo do CloudPanel usa 64 MB).

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
   | `SESSION_DOMAIN` | vazio (cookie só do domínio principal; o `www` é redirecionado) |
   | `DB_*` | banco MySQL |
   | `SANCTA_ADMIN_PATH` | um caminho difícil de adivinhar, ex.: `gestao-7f3k9` |
   | `SANCTA_FAKE_PAYMENTS` | `false` (o simulado já é ignorado em produção) |
   | `TRUSTED_PROXIES` | `cloudflare` atrás da Cloudflare; os IPs do proxy em outros casos; vazio sem proxy. Sem isso, todo visitante aparece com o IP do proxy (limite de login compartilhado, restrição de IP do painel errada) |
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

### VPS Oracle Cloud com CloudPanel e Cloudflare

A pasta [`deploy/cloudpanel/`](../deploy/cloudpanel/) instala o CloudPanel numa VPS Ubuntu 24.04 da Oracle (x86 ou ARM/Ampere), já protegido, e publica o Sancta nele atrás da Cloudflare. A mesma instalação serve para os próximos sites do CloudPanel. Uma VPS de 2 CPUs e 12 GB atende o lançamento com folga.

**Como o tráfego chega**

```
visitante / app ──HTTPS──▶ Cloudflare (WAF, cache, certificado público)
                              │ HTTPS "Full (strict)", certificado de origem da Cloudflare
                              ▼
              VPS Oracle: só 80/443 abertas ── Nginx do CloudPanel ── PHP-FPM ── Laravel
                              │ o site do Sancta só aceita conexões vindas da Cloudflare
              MySQL, Redis e Postfix só dentro da máquina · painel do CloudPanel só por túnel SSH
```

| Porta | Situação | Para quê |
|---|---|---|
| 22 | aberta na Oracle e no UFW | SSH só com chave, sem root, com fail2ban |
| 80 e 443 | abertas na Oracle e no UFW | sites; o do Sancta recusa quem não vem da Cloudflare |
| 8443 | **fechada na Oracle** | painel do CloudPanel, acessado por túnel SSH |
| 3306, 6379, 25 | só `127.0.0.1` | MySQL, Redis, Postfix |

**1. No console da Oracle (uma vez)**
- **Instância:** imagem **Ubuntu 24.04** (no 22.04 o CloudPanel só oferece MySQL 8.0), com a sua chave SSH.
- **IP fixo:** Networking → IP Management → Reserved public IPs → reserve um IP e associe à VPS.
- **Portas:** na VCN → Security Lists (ou NSG) → Add Ingress Rules, origem `0.0.0.0/0`, TCP, porta de **destino** `22`, depois `80` e `443`. **Não** crie regra para a `8443`, mesmo que o guia do CloudPanel mande.
- **Conta:** numa conta só "Always Free", a Oracle pode recolher máquinas ociosas por 7 dias. Passe para **Pay As You Go** (o gratuito continua gratuito) e crie um alerta em Billing → Budgets.

**2. Instalar o CloudPanel.** No seu computador, de dentro da pasta do projeto, copie a pasta para a VPS e entre nela (troque o caminho da chave e o IP):
```powershell
scp -i C:\caminho\chave-oracle.key -r deploy\cloudpanel ubuntu@IP-DA-VPS:~/
```
```powershell
ssh -i C:\caminho\chave-oracle.key ubuntu@IP-DA-VPS
```
Na VPS:
```bash
sudo bash ~/cloudpanel/instalar-cloudpanel.sh
```
- Ele pergunta usuário, e-mail e nome do administrador do CloudPanel. A senha é sorteada e aparece **uma única vez**: anote.
- O instalador oficial só roda se o SHA-256 bater com o publicado pelo CloudPanel. Se o CloudPanel lançar versão nova, o script para e explica como informar o SHA-256 novo, que fica na [página oficial](https://www.cloudpanel.io/docs/v2/getting-started/oracle-cloud/installation/installer/).
- Ao final ele oferece reiniciar a VPS, como a Oracle recomenda.

O que ele deixa configurado:
- **Firewall:** o UFW do CloudPanel manda nas portas. As regras de bloqueio da imagem da Oracle saem do iptables, porque viriam antes das do UFW e manteriam 80/443 fechadas; o original fica em `/etc/iptables/rules.v4.oracle-original`. A cadeia `InstanceServices`, que protege o serviço de metadados da Oracle, é mantida.
- **Administrador:** criado pela linha de comando, então a tela de "primeiro acesso" do CloudPanel nunca fica aberta para robôs.
- **Proteção da máquina:** SSH só com chave e sem root, fail2ban e atualizações automáticas de segurança. O Postfix, o MySQL e o Redis ficam ouvindo só dentro da máquina.
- **IPs da Cloudflare:** o `cloudflare-ips` atualiza toda segunda-feira a lista usada pela trava "só Cloudflare" (`/etc/nginx/cloudflare/allow.conf`).

**3. Entrar no CloudPanel** (sempre por túnel). No seu computador, deixe esta janela aberta:
```powershell
ssh -i C:\caminho\chave-oracle.key -N -L 8443:127.0.0.1:8443 ubuntu@IP-DA-VPS
```
Abra <https://localhost:8443> e aceite o aviso do certificado. Logo no primeiro acesso:
- ative o **2FA** no seu perfil;
- configure **Admin Area → Backups** com um destino fora da VPS (Google Drive, Dropbox, S3, Wasabi…). Ele copia a pasta de cada site, inclusive o `.env` e os uploads, e os backups do banco que o CloudPanel faz toda noite às 03:15 (guardados 7 dias em `/home/<usuário>/backups`).

**4. Domínio e Cloudflare** (depois de registrar o `sancta.app.br` no Registro.br)
- **Nameservers:** crie uma conta grátis na Cloudflare e adicione o site `sancta.app.br`. No Registro.br, troque os servidores DNS pelos dois que a Cloudflare indicar.
- **DNS:** registro `A` do `sancta.app.br` apontando para o IP reservado, e `CNAME` do `www` para `sancta.app.br`. Os dois com a **nuvem laranja** (Proxied).
- **SSL/TLS → Overview:** modo **Full (strict)**.
- **SSL/TLS → Edge Certificates:** liga *Always Use HTTPS*, *Minimum TLS* 1.2 e *TLS 1.3*. O HSTS da Cloudflare fica desligado, porque o Sancta já envia o seu.
- **SSL/TLS → Origin Server → Create Certificate:** RSA, `sancta.app.br` e `*.sancta.app.br`, 15 anos. Deixe a tela aberta: o certificado e a chave são colados no passo 5, e a chave não deve ir para nenhum outro lugar.
- **Rules → Redirect Rules:** use o modelo *Redirect from WWW to root* (301).
- **Desligue o que quebra o app ou a segurança do site:**
  - *Security → Bots → Bot Fight Mode:* bloqueia o app e os avisos de pagamento do Mercado Pago e do Google, e no plano grátis não dá para abrir exceções.
  - *Speed → Rocket Loader* e *Scrape Shield → Email Address Obfuscation:* injetam scripts que a política de segurança (CSP) do site bloqueia.
  - Não crie regra de cache *Cache Everything* nem ligue o *Under Attack Mode* no site inteiro: as respostas da API não podem ser guardadas, e o app não passa por desafio de navegador.
- **Opcional:** em *Security → WAF → Custom rules*, bloqueie o caminho do painel do Sancta (`/gestao-...`) para quem está fora do Brasil (ou fora do seu IP).

**5. Publicar o Sancta.** Na VPS:
```bash
sudo bash ~/cloudpanel/instalar-sancta.sh
```
- Pergunta domínio (`sancta.app.br`), usuário do site (`sancta`), versão do PHP (8.3), e-mail do super administrador e repositório.
- Cadastra o modelo de vhost **Sancta** e cria o site, o usuário do site e o banco (senha sorteada) pelo `clpctl`.
- Pede para colar o certificado de origem e a chave (a chave não aparece na tela), confere se os dois combinam e os instala.
- Mostra uma **deploy key**: cadastre em GitHub → repositório `sancta` → Settings → Deploy keys, com **Allow write access desmarcado**.
- Cria o `.env` de produção (`TRUSTED_PROXIES=cloudflare`, caminho do painel sorteado), roda Composer, migrations, dados iniciais e caches. **Anote a senha do super administrador** da etapa "Dados iniciais".
- Agenda o Laravel em `/etc/cron.d/sancta`, e não no crontab do usuário, que o CloudPanel regrava quando se mexe na aba Cron Jobs.
- No fim, testa o site no próprio servidor e pela Cloudflare.

Depois, no CloudPanel → Sites → `sancta.app.br` → **Settings → PHP Settings**, confira `memory_limit` 256M, `max_execution_time` 120, `post_max_size` 64M e `upload_max_filesize` 64M.

**Como o site do Sancta fica no CloudPanel.** O modelo de vhost [`sancta-vhost.tpl`](../deploy/cloudpanel/sancta-vhost.tpl) parte do "Laravel 12" oficial do CloudPanel, com estas mudanças:
- só aceita conexões da Cloudflare;
- só executa o `index.php`;
- nunca serve arquivos ocultos;
- manda para o Laravel os arquivos estáticos que não existem (no oficial, o JavaScript do Livewire daria 404 e o painel quebraria);
- aceita uploads de 64 MB;
- não troca as respostas de erro da API por páginas do Nginx.

A pasta pública é `backend/public`, e o código fica em `/home/sancta/htdocs/sancta.app.br`.

**Atualizar** depois de enviar mudanças ao `main` no GitHub:
```bash
sudo sancta-atualizar
```
Põe o site em manutenção por alguns segundos, baixa o código, instala dependências, roda as migrations, recria os caches e recarrega o PHP. Se algo falhar, o site fica em manutenção e a mensagem diz como seguir. Ele também avisa quando o `.env.example` ganhou variáveis que o `.env` de produção ainda não tem.

**Outros sites no mesmo CloudPanel.** Para que um site também só aceite a Cloudflare, acrescente no Vhost dele (aba Vhost do site):
```nginx
set_real_ip_from 127.0.0.1;
include /etc/nginx/cloudflare/allow.conf;
```
Configure a aplicação para confiar nas faixas da Cloudflare (lista em `/etc/nginx/cloudflare/ips.txt`). Bloqueios de IP de visitantes se fazem na Cloudflare, não no CloudPanel: para o servidor, todo acesso vem dela.

**Problemas comuns**
| Sintoma | Causa provável |
|---|---|
| Erro 521 ou 522 da Cloudflare | Portas 80/443 fechadas na Security List da Oracle |
| Erro 526 | Certificado de origem não instalado, ou SSL fora do modo Full (strict) |
| 403 para todo mundo | A nuvem do DNS está cinza (sem Cloudflare). Volte para laranja ou, temporariamente, `sudo cloudflare-ips --desligar` (religue com `sudo cloudflare-ips`) |
| App sem conexão, pagamentos sem confirmação | Bot Fight Mode ligado, ou regra de desafio/cache na API |
| Upload de áudio falha | PHP Settings do site abaixo de 64M |

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
- [ ] Backups automáticos do banco e de `storage/app`, com cópia fora da VPS (CloudPanel → Admin Area → Backups)
- [ ] Porta 8443 fechada na Oracle (CloudPanel só por túnel SSH) e 2FA ligado no CloudPanel
- [ ] Atrás da Cloudflare: `TRUSTED_PROXIES=cloudflare`, SSL Full (strict), Bot Fight Mode e Rocket Loader desligados
- [ ] Senha do super administrador trocada; cada membro da equipe com conta própria
- [ ] Textos de Termos e Privacidade revisados por um advogado (modelos iniciais no painel → Site → Páginas)
- [ ] Licença do texto bíblico resolvida (ver SOBRE_O_PROJETO.md → Riscos)
