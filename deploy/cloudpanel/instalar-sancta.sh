#!/usr/bin/env bash
# Sancta — cria o site no CloudPanel e publica o sistema, atrás da Cloudflare.
#
# Antes: rode o instalar-cloudpanel.sh, coloque o domínio na Cloudflare (DNS com a nuvem laranja,
# SSL "Full (strict)") e gere o certificado de origem (veja docs/IMPLANTACAO.md).
# Uso, na VPS, dentro da pasta copiada do repositório (deploy/cloudpanel):
#   sudo bash instalar-sancta.sh
#
# O que faz: cadastra o modelo de vhost "Sancta"; cria o site, o usuário do site e o banco pelo
# clpctl; instala o certificado de origem da Cloudflare; baixa o código com uma deploy key somente
# leitura; cria o .env de produção (caminho do painel sorteado, TRUSTED_PROXIES=cloudflare); roda
# Composer, migrations, dados iniciais e caches; agenda o Laravel; instala o sancta-atualizar.
# Pode ser executado de novo: cada etapa pula o que já está feito.
#
# Respostas também por variáveis de ambiente: DOMINIO, SITE_USUARIO, PHPV, EMAIL_ADMIN, REPO.
set -Eeuo pipefail

AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_PADRAO=git@github.com:tech-tiago/sancta.git

passo() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
aviso() { printf '\033[1;33m[!] %s\033[0m\n' "$*"; }
ok() { printf '\033[1;32m[ok] %s\033[0m\n' "$*"; }
falha() { printf '\033[1;31m[x] %s\033[0m\n' "$*" >&2; exit 1; }
trap 'falha "Parou na linha $LINENO. Corrija o problema e rode o script de novo: ele continua de onde parou."' ERR

pergunta() { # pergunta VARIAVEL "texto" [padrão]
    local var=$1 texto=$2 padrao=${3:-} resposta
    if [[ -n "${!var:-}" ]]; then return 0; fi
    read -r -p "$texto${padrao:+ [$padrao]}: " resposta
    printf -v "$var" '%s' "${resposta:-$padrao}"
}
senha() { openssl rand -base64 32 | tr -d '/+=' | cut -c1-32; }

#──────────────── Conferências e perguntas ────────────────
[[ $EUID -eq 0 ]] || falha "Rode com sudo: sudo bash instalar-sancta.sh"
command -v clpctl > /dev/null || falha "CloudPanel não encontrado. Rode antes: sudo bash instalar-cloudpanel.sh"
for arquivo in sancta-vhost.tpl atualizar.sh; do
    [[ -f "$AQUI/$arquivo" ]] || falha "$arquivo não está ao lado deste script. Copie a pasta cloudpanel inteira."
done
[[ -x /usr/local/sbin/cloudflare-ips ]] || falha "cloudflare-ips não instalado. Rode antes: sudo bash instalar-cloudpanel.sh"

pergunta DOMINIO "Domínio do Sancta" "sancta.app.br"
DOMINIO="${DOMINIO,,}"
[[ "$DOMINIO" =~ ^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$ ]] || falha "Domínio inválido: $DOMINIO"
pergunta SITE_USUARIO "Usuário do site no CloudPanel" "sancta"
[[ "$SITE_USUARIO" =~ ^[a-z][a-z0-9-]{2,31}$ ]] || falha "Usuário do site inválido: $SITE_USUARIO"
pergunta PHPV "Versão do PHP" "8.3"
[[ "$PHPV" =~ ^8\.[2-9]$ ]] || falha "Versão do PHP inválida: $PHPV (o Sancta precisa do 8.2 ou mais novo)."
PHP="/usr/bin/php$PHPV"
pergunta EMAIL_ADMIN "E-mail do super administrador do painel do Sancta"
[[ "$EMAIL_ADMIN" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || falha "E-mail inválido: $EMAIL_ADMIN"
pergunta REPO "Repositório" "$REPO_PADRAO"

CASA="/home/$SITE_USUARIO"
APP_DIR="$CASA/htdocs/$DOMINIO"
BACKEND="$APP_DIR/backend"
como_site() { (cd / && sudo -u "$SITE_USUARIO" -H "$@"); }
artisan() { como_site "$PHP" "$BACKEND/artisan" "$@"; }

#──────────────── Site no CloudPanel ────────────────
passo "Modelo de vhost \"Sancta\""
if clpctl vhost-templates:list 2> /dev/null | grep -qw Sancta; then
    echo "Modelo já cadastrado."
else
    modelo="$(mktemp --suffix=.tpl)"
    sed "1s/\"phpVersion\":\"[0-9.]*\"/\"phpVersion\":\"$PHPV\"/" "$AQUI/sancta-vhost.tpl" > "$modelo"
    if ! nginx -V 2>&1 | grep -q -- '--with-http_realip_module'; then
        sed -i '/set_real_ip_from 127.0.0.1;/d' "$modelo" # sem o módulo, não há troca de IP a desligar
    fi
    clpctl vhost-template:add --name='Sancta' --file="$modelo"
    rm -f "$modelo"
fi

passo "Site $DOMINIO (PHP $PHPV)"
if [[ -d "$APP_DIR" ]]; then
    echo "Site já existe."
else
    SITE_SENHA="$(senha)"
    clpctl site:add:php --domainName="$DOMINIO" --phpVersion="$PHPV" --vhostTemplate='Sancta' \
        --siteUser="$SITE_USUARIO" --siteUserPassword="$SITE_SENHA"
    [[ -d "$APP_DIR" ]] || falha "O CloudPanel não criou $APP_DIR."
fi
[[ -x "$PHP" ]] || falha "PHP $PHPV não encontrado em $PHP."
modulos="$("$PHP" -m)"
for ext in pdo_mysql mbstring openssl intl gd curl fileinfo sodium zip bcmath dom xml tokenizer ctype; do
    grep -qix "$ext" <<< "$modulos" || falha "O PHP $PHPV do CloudPanel está sem a extensão $ext."
done

achar_vhost() { grep -rlF --exclude-dir=cloudflare -- "$APP_DIR" /etc/nginx/ 2> /dev/null | head -1 || true; }
vhost="$(achar_vhost)"
until [[ -n "$vhost" ]] && grep -qE -- "root[[:space:]]+$BACKEND/public;" "$vhost"; do
    aviso "A pasta pública do site não é $BACKEND/public."
    echo "No CloudPanel: Sites → $DOMINIO → Settings → Root Directory = backend/public → Save."
    read -r -p "Depois de salvar, tecle Enter para conferir de novo (ou digite pular): " resposta
    [[ "$resposta" == pular ]] && break
    vhost="$(achar_vhost)"
done
[[ "${resposta:-}" == pular ]] || ok "Vhost $vhost aponta para backend/public."

passo "Certificado de origem da Cloudflare"
pasta_cert=/etc/ssl/cloudflare-origin
cert="$pasta_cert/$DOMINIO.crt"
chave="$pasta_cert/$DOMINIO.key"
instalado="$pasta_cert/$DOMINIO.instalado"
if [[ -f "$instalado" ]]; then
    echo "Certificado já instalado (válido até $(openssl x509 -noout -enddate -in "$cert" | cut -d= -f2))."
else
    install -d -m 700 "$pasta_cert"
    ler_pem() { # ler_pem "fim do bloco" arquivo [-s para não mostrar na tela]
        local fim=$1 destino=$2 linha
        : > "$destino"
        chmod 600 "$destino"
        while IFS= read -r ${3:-} linha; do
            printf '%s\n' "$linha" >> "$destino"
            [[ "$linha" == *"$fim"* ]] && break
        done
    }
    if [[ ! -s "$cert" || ! -s "$chave" ]]; then
        echo "Cloudflare → SSL/TLS → Origin Server → Create Certificate (RSA, $DOMINIO e *.$DOMINIO, 15 anos)."
        echo "Cole o \"Origin Certificate\" inteiro (de BEGIN a END CERTIFICATE) e tecle Enter:"
        ler_pem "END CERTIFICATE" "$cert"
        echo "Agora cole a \"Private Key\" inteira (de BEGIN a END PRIVATE KEY). Ela não aparece na tela:"
        ler_pem "END PRIVATE KEY" "$chave" -s
        echo
    fi
    openssl x509 -noout -in "$cert" 2> /dev/null || { rm -f "$cert" "$chave"; falha "Certificado inválido. Rode de novo e cole o bloco inteiro."; }
    openssl pkey -noout -in "$chave" 2> /dev/null || { rm -f "$cert" "$chave"; falha "Chave inválida. Rode de novo e cole o bloco inteiro."; }
    [[ "$(openssl x509 -noout -pubkey -in "$cert" | sha256sum)" == "$(openssl pkey -pubout -in "$chave" | sha256sum)" ]] \
        || { rm -f "$cert" "$chave"; falha "A chave não é deste certificado."; }
    if ! clpctl site:install:certificate --domainName="$DOMINIO" --privateKey="$chave" --certificate="$cert"; then
        # Se o CloudPanel exigir a cadeia, ela é a raiz da Origin CA da Cloudflare (RSA ou ECC).
        tipo=rsa
        openssl x509 -noout -issuer -in "$cert" | grep -qi ecc && tipo=ecc
        curl -fsS "https://developers.cloudflare.com/ssl/static/origin_ca_${tipo}_root.pem" -o "$pasta_cert/origin_ca_${tipo}_root.pem"
        clpctl site:install:certificate --domainName="$DOMINIO" --privateKey="$chave" --certificate="$cert" \
            --certificateChain="$pasta_cert/origin_ca_${tipo}_root.pem"
    fi
    touch "$instalado"
    ok "Certificado de origem instalado (válido até $(openssl x509 -noout -enddate -in "$cert" | cut -d= -f2))."
fi

#──────────────── Código ────────────────
passo "Acesso do servidor ao GitHub (deploy key somente leitura)"
install -d -m 700 -o "$SITE_USUARIO" -g "$(id -gn "$SITE_USUARIO")" "$CASA/.ssh"
if [[ ! -f "$CASA/.ssh/id_ed25519" ]]; then
    como_site ssh-keygen -q -t ed25519 -N "" -C "sancta-cloudpanel" -f "$CASA/.ssh/id_ed25519"
fi
# Chaves do GitHub obtidas por HTTPS da API oficial, para não confiar às cegas na primeira conexão.
curl -fsSL https://api.github.com/meta \
    | python3 -c 'import json, sys; [print("github.com", k) for k in json.load(sys.stdin)["ssh_keys"]]' \
    > "$CASA/.ssh/known_hosts"
chown "$SITE_USUARIO:$(id -gn "$SITE_USUARIO")" "$CASA/.ssh/known_hosts"
chmod 644 "$CASA/.ssh/known_hosts"

if [[ ! -d "$APP_DIR/.git" ]]; then
    until como_site git ls-remote "$REPO" > /dev/null 2>&1; do
        echo
        echo "Cadastre esta chave no GitHub: repositório → Settings → Deploy keys → Add deploy key"
        echo "(título: VPS Oracle; deixe \"Allow write access\" DESMARCADO):"
        echo
        cat "$CASA/.ssh/id_ed25519.pub"
        echo
        read -r -p "Depois de salvar no GitHub, tecle Enter para testar de novo... "
    done
    passo "Baixando o código"
    if [[ -n "$(ls -A "$APP_DIR")" ]]; then
        # Arquivos de exemplo que o CloudPanel põe na pasta nova: guardados, não apagados.
        guarda="$CASA/cloudpanel-original-$(date +%Y%m%d%H%M%S)"
        como_site mkdir -p "$guarda"
        find "$APP_DIR" -mindepth 1 -maxdepth 1 -exec mv -t "$guarda" {} +
    fi
    como_site git clone -q "$REPO" "$APP_DIR"
fi

#──────────────── Banco e .env ────────────────
if [[ ! -f "$BACKEND/.env" ]]; then
    passo "Banco de dados"
    DB_SENHA="$(senha)"
    clpctl db:add --domainName="$DOMINIO" --databaseName=sancta --databaseUserName=sancta --databaseUserPassword="$DB_SENHA"

    passo "Arquivo .env de produção"
    install -m 600 -o "$SITE_USUARIO" -g "$(id -gn "$SITE_USUARIO")" "$BACKEND/.env.example" "$BACKEND/.env"
    definir() { # definir CHAVE valor (substitui a linha ou acrescenta no fim)
        local chave=$1 valor=$2
        valor="${valor//\\/\\\\}"
        valor="${valor//&/\\&}"
        valor="${valor//|/\\|}"
        if grep -q "^$chave=" "$BACKEND/.env"; then
            sed -i "s|^$chave=.*|$chave=$valor|" "$BACKEND/.env"
        else
            printf '%s=%s\n' "$chave" "$valor" >> "$BACKEND/.env"
        fi
    }
    definir APP_ENV production
    definir APP_DEBUG false
    definir APP_URL "https://$DOMINIO"
    definir LOG_LEVEL warning
    definir DB_HOST 127.0.0.1
    definir DB_DATABASE sancta
    definir DB_USERNAME sancta
    definir DB_PASSWORD "$DB_SENHA"
    definir MAIL_FROM_ADDRESS "\"nao-responda@$DOMINIO\""
    definir TRUSTED_PROXIES cloudflare
    definir SANCTA_ADMIN_PATH "gestao-$(openssl rand -hex 3)"
    definir SANCTA_ADMIN_EMAIL "$EMAIL_ADMIN"
    definir SANCTA_ADMIN_PASSWORD ""
    definir SANCTA_FAKE_PAYMENTS false
fi

#──────────────── Aplicação ────────────────
passo "Composer"
COMPOSER="$(command -v composer || true)"
if [[ -z "$COMPOSER" ]]; then
    esperado="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    obtido="$("$PHP" -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [[ "$esperado" == "$obtido" ]] || falha "A assinatura do instalador do Composer não confere."
    "$PHP" /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
    COMPOSER=/usr/local/bin/composer
fi
como_site "$PHP" "$COMPOSER" install -d "$BACKEND" --no-dev --optimize-autoloader --no-interaction --no-progress

passo "Chave da aplicação e migrations"
grep -q '^APP_KEY=base64:' "$BACKEND/.env" || artisan key:generate --force
artisan migrate --force
if [[ ! -f "$BACKEND/storage/app/.dados-iniciais" ]]; then
    passo "Dados iniciais"
    aviso "ANOTE a senha do super administrador do Sancta que aparece abaixo: ela é mostrada uma única vez."
    artisan db:seed --force
    como_site touch "$BACKEND/storage/app/.dados-iniciais"
fi
[[ -L "$BACKEND/public/storage" ]] || artisan storage:link

passo "IPs da Cloudflare também para o Laravel"
linha_copia="$BACKEND/storage/app/cloudflare-ips.txt $SITE_USUARIO"
grep -qxF -- "$linha_copia" /etc/cloudflare-ips/copias.conf || echo "$linha_copia" >> /etc/cloudflare-ips/copias.conf
/usr/local/sbin/cloudflare-ips

passo "Caches de produção"
artisan optimize
artisan filament:optimize

passo "Agendador, atualização e configuração do servidor"
install -d -m 755 /etc/sancta
cat > /etc/sancta/sancta.conf << EOF
# Lido pelo sancta-atualizar. Gerado pelo instalar-sancta.sh.
DOMINIO=$DOMINIO
SITE_USUARIO=$SITE_USUARIO
PHPV=$PHPV
APP_DIR=$APP_DIR
EOF
chmod 644 /etc/sancta/sancta.conf
install -m 750 -o root -g root "$AQUI/atualizar.sh" /usr/local/sbin/sancta-atualizar
# Fora do crontab do usuário (que o CloudPanel regrava quando se mexe na aba Cron Jobs).
cat > /etc/cron.d/sancta << EOF
# Sancta: agendador do Laravel (notícias, liturgia, assinaturas, push, ao vivo, limpezas).
SHELL=/bin/bash
* * * * * $SITE_USUARIO cd $BACKEND && $PHP artisan schedule:run >> /dev/null 2>&1
EOF
chmod 644 /etc/cron.d/sancta

systemctl reload "php$PHPV-fpm" 2> /dev/null || aviso "Não consegui recarregar o php$PHPV-fpm; reinicie o PHP pelo CloudPanel se o site mostrar código antigo."
nginx -t -q
systemctl reload nginx

#──────────────── Verificação ────────────────
passo "Verificação"
local_up="$(curl -sk -o /dev/null -w '%{http_code}' --resolve "$DOMINIO:443:127.0.0.1" "https://$DOMINIO/up")"
local_api="$(curl -sk -o /dev/null -w '%{http_code}' --resolve "$DOMINIO:443:127.0.0.1" "https://$DOMINIO/api/v1/config")"
publico="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "https://$DOMINIO/up" || true)"
echo "No servidor: /up HTTP $local_up · API HTTP $local_api · Pela Cloudflare: HTTP ${publico:-sem resposta}"
[[ "$local_up" == 200 && "$local_api" == 200 ]] || aviso "Esperava 200 no servidor. Veja $BACKEND/storage/logs e o log do site no CloudPanel."
if [[ "$publico" != 200 ]]; then
    aviso "Pela Cloudflare ainda não respondeu 200. Confira: DNS com a nuvem laranja apontando para esta VPS, SSL \"Full (strict)\", portas 80/443 abertas na Oracle. 526 = certificado; 522/521 = porta fechada."
fi

painel="$(grep '^SANCTA_ADMIN_PATH=' "$BACKEND/.env" | cut -d= -f2)"
printf '\n\033[1;32mSancta publicado no CloudPanel.\033[0m\n'
echo "  Site:    https://$DOMINIO"
echo "  Painel:  https://$DOMINIO/$painel"
echo "  Login:   $EMAIL_ADMIN (senha mostrada na etapa \"Dados iniciais\")"
if [[ -n "${SITE_SENHA:-}" ]]; then
    echo "  Usuário do site no CloudPanel: $SITE_USUARIO / $SITE_SENHA (SFTP só com chave SSH; guarde a senha)"
fi
echo
echo "  No CloudPanel → Sites → $DOMINIO → Settings → PHP Settings, confira:"
echo "    memory_limit 256M · max_execution_time 120 · post_max_size 64M · upload_max_filesize 64M"
echo "  Depois de enviar mudanças ao GitHub:  sudo sancta-atualizar"
