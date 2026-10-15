#!/usr/bin/env bash
# Sancta — instalação numa VPS Ubuntu 22.04 ou 24.04 da Oracle Cloud (x86 ou ARM/Ampere).
#
# Uso, na VPS, dentro da pasta copiada do repositório:
#   sudo bash instalar.sh
#
# Instala Nginx, PHP 8.3, MySQL 8, Composer e Certbot; libera as portas 80/443 no firewall da
# máquina; protege o SSH; cria o usuário "sancta"; baixa o código com uma deploy key somente
# leitura; cria o banco e o .env de produção; roda migrations e dados iniciais; configura HTTPS,
# agendador e backup diário. Pode ser executado de novo: cada etapa pula o que já está feito.
#
# As respostas também podem vir por variáveis de ambiente (DOMINIO, COM_WWW, EMAIL_CERTIFICADO,
# EMAIL_ADMIN, REPO), por exemplo: sudo DOMINIO=sancta.app.br bash instalar.sh
set -Eeuo pipefail

PHPV=8.3
APP_USER=sancta
APP_DIR=/var/www/sancta
BACKEND=$APP_DIR/backend
REPO_PADRAO=git@github.com:tech-tiago/sancta.git
AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

passo() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
aviso() { printf '\033[1;33m[!] %s\033[0m\n' "$*"; }
falha() { printf '\033[1;31m[x] %s\033[0m\n' "$*" >&2; exit 1; }
trap 'falha "Parou na linha $LINENO. Corrija o problema e rode o script de novo: ele continua de onde parou."' ERR

# Executa como o usuário sancta, a partir de "/" (o git recusa rodar numa pasta que ele não lê).
como_sancta() { (cd / && sudo -u "$APP_USER" -H "$@"); }
artisan() { como_sancta php "$BACKEND/artisan" "$@"; }

pergunta() { # pergunta VARIAVEL "texto" [padrão]
    local var=$1 texto=$2 padrao=${3:-} resposta
    if [[ -n "${!var:-}" ]]; then return 0; fi
    read -r -p "$texto${padrao:+ [$padrao]}: " resposta
    printf -v "$var" '%s' "${resposta:-$padrao}"
}

#──────────────── Conferências e perguntas ────────────────
[[ $EUID -eq 0 ]] || falha "Rode com sudo: sudo bash instalar.sh"
. /etc/os-release
[[ "${ID:-}" == ubuntu ]] || falha "Este instalador é para Ubuntu (encontrado: ${PRETTY_NAME:-desconhecido})."
case "${VERSION_ID:-}" in
    22.04 | 24.04) ;;
    *) aviso "Testado no Ubuntu 22.04 e 24.04; esta máquina tem o ${VERSION_ID:-?}. Continuando." ;;
esac
for arquivo in nginx-sancta.conf php-fpm-sancta.conf php-sancta.ini mysql-sancta.cnf atualizar.sh backup.sh; do
    [[ -f "$AQUI/$arquivo" ]] || falha "Arquivo $arquivo não encontrado ao lado do instalar.sh. Copie a pasta oracle-ubuntu inteira."
done
USUARIO_SSH="${SUDO_USER:-ubuntu}"

pergunta DOMINIO "Domínio do Sancta (ex.: sancta.app.br)"
DOMINIO="${DOMINIO,,}"
DOMINIO="${DOMINIO#http://}"
DOMINIO="${DOMINIO#https://}"
DOMINIO="${DOMINIO%%/*}"
DOMINIO="${DOMINIO#www.}"
[[ "$DOMINIO" =~ ^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$ ]] || falha "Domínio inválido: $DOMINIO"
pergunta COM_WWW "Atender também www.$DOMINIO (redirecionando para $DOMINIO)? (s/n)" "s"
pergunta EMAIL_CERTIFICADO "E-mail para avisos do certificado HTTPS (Let's Encrypt)"
[[ "$EMAIL_CERTIFICADO" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || falha "E-mail inválido: $EMAIL_CERTIFICADO"
pergunta EMAIL_ADMIN "E-mail do super administrador do painel"
[[ "$EMAIL_ADMIN" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || falha "E-mail inválido: $EMAIL_ADMIN"
pergunta REPO "Repositório" "$REPO_PADRAO"

#──────────────── Sistema ────────────────
passo "Atualizando o sistema e instalando os pacotes"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get -y -q -o Dpkg::Options::=--force-confold upgrade
apt-get install -y -q ca-certificates curl git unzip software-properties-common openssl \
    nginx mysql-server certbot python3-certbot-nginx fail2ban unattended-upgrades

passo "PHP $PHPV"
if ! apt-cache show "php$PHPV-fpm" > /dev/null 2>&1; then
    # O Ubuntu 22.04 traz o PHP 8.1; o PPA do mantenedor do PHP no Debian/Ubuntu tem o 8.3.
    add-apt-repository -y ppa:ondrej/php
    apt-get update -q
fi
apt-get install -y -q php$PHPV-{fpm,cli,mysql,mbstring,xml,curl,intl,gd,zip,bcmath,opcache,readline}
update-alternatives --set php "/usr/bin/php$PHPV" > /dev/null
modulos="$(php -m)"
for ext in pdo_mysql mbstring openssl intl gd curl fileinfo sodium zip bcmath dom xml tokenizer ctype; do
    grep -qix "$ext" <<< "$modulos" || falha "Extensão do PHP ausente: $ext"
done

passo "Composer"
if ! command -v composer > /dev/null; then
    esperado="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    obtido="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [[ "$esperado" == "$obtido" ]] || falha "A assinatura do instalador do Composer não confere."
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi

passo "Fuso horário e memória de troca"
timedatectl set-timezone America/Sao_Paulo
if ! swapon --show | grep -q .; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile > /dev/null
    swapon /swapfile
    grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

passo "Firewall da máquina: portas 80 e 443"
# As imagens Ubuntu da Oracle trazem regras iptables que recusam tudo além do SSH. A Oracle
# desaconselha o ufw nessas imagens; as regras vão direto no iptables e são salvas.
if iptables -S INPUT | grep -q -- '-j REJECT'; then
    for porta in 443 80; do
        if ! iptables -C INPUT -p tcp -m state --state NEW --dport "$porta" -j ACCEPT 2> /dev/null; then
            linha="$(iptables -L INPUT --line-numbers -n | awk '$2 == "REJECT" { print $1; exit }')"
            iptables -I INPUT "$linha" -p tcp -m state --state NEW --dport "$porta" -j ACCEPT
        fi
    done
    command -v netfilter-persistent > /dev/null || apt-get install -y -q iptables-persistent
    netfilter-persistent save > /dev/null
else
    echo "Nenhuma regra de bloqueio no iptables: nada a liberar aqui."
fi
aviso "Libere também as portas 80 e 443 na Security List da VCN, no console da Oracle."

passo "SSH: só com chave e nunca como root"
casa_ssh="$(getent passwd "$USUARIO_SSH" | cut -d: -f6)"
if [[ -s "$casa_ssh/.ssh/authorized_keys" ]]; then
    cat > /etc/ssh/sshd_config.d/10-sancta.conf << 'EOF'
# Sancta: acesso só por chave e nunca como root.
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
EOF
    sshd -t
    systemctl try-reload-or-restart ssh
else
    aviso "Não encontrei chave SSH para $USUARIO_SSH; deixei o SSH como estava."
fi

passo "Atualizações automáticas de segurança e fail2ban"
cat > /etc/apt/apt.conf.d/20auto-upgrades << 'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF
systemctl enable --now fail2ban unattended-upgrades > /dev/null 2>&1

#──────────────── Código ────────────────
passo "Usuário $APP_USER e pasta $APP_DIR"
id "$APP_USER" > /dev/null 2>&1 || adduser --disabled-password --gecos "" "$APP_USER" > /dev/null
usermod -aG "$APP_USER" www-data # o Nginx (www-data) lê os arquivos públicos
install -d -m 750 -o "$APP_USER" -g "$APP_USER" "$APP_DIR"
casa_app="$(getent passwd "$APP_USER" | cut -d: -f6)"

passo "Acesso do servidor ao GitHub (deploy key somente leitura)"
install -d -m 700 -o "$APP_USER" -g "$APP_USER" "$casa_app/.ssh"
if [[ ! -f "$casa_app/.ssh/id_ed25519" ]]; then
    como_sancta ssh-keygen -q -t ed25519 -N "" -C "sancta-vps" -f "$casa_app/.ssh/id_ed25519"
fi
# Chaves do GitHub obtidas por HTTPS da API oficial, para não confiar às cegas na primeira conexão.
curl -fsSL https://api.github.com/meta \
    | python3 -c 'import json, sys; [print("github.com", k) for k in json.load(sys.stdin)["ssh_keys"]]' \
    > "$casa_app/.ssh/known_hosts"
chown "$APP_USER:$APP_USER" "$casa_app/.ssh/known_hosts"
chmod 644 "$casa_app/.ssh/known_hosts"

if [[ ! -d "$APP_DIR/.git" ]]; then
    until como_sancta git ls-remote "$REPO" > /dev/null 2>&1; do
        echo
        echo "Cadastre esta chave no GitHub: repositório → Settings → Deploy keys → Add deploy key"
        echo "(título: VPS Oracle; deixe \"Allow write access\" DESMARCADO):"
        echo
        cat "$casa_app/.ssh/id_ed25519.pub"
        echo
        read -r -p "Depois de salvar no GitHub, tecle Enter para testar de novo... "
    done
    passo "Baixando o código"
    como_sancta git clone -q "$REPO" "$APP_DIR"
fi

#──────────────── Banco e .env ────────────────
passo "MySQL"
install -m 644 "$AQUI/mysql-sancta.cnf" /etc/mysql/mysql.conf.d/zz-sancta.cnf
systemctl restart mysql
if [[ ! -f "$BACKEND/.env" ]]; then
    DB_SENHA="$(openssl rand -hex 24)"
    mysql << SQL
CREATE DATABASE IF NOT EXISTS sancta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'sancta'@'localhost' IDENTIFIED BY '$DB_SENHA';
ALTER USER 'sancta'@'localhost' IDENTIFIED BY '$DB_SENHA';
GRANT ALL PRIVILEGES ON sancta.* TO 'sancta'@'localhost';
FLUSH PRIVILEGES;
SQL

    passo "Arquivo .env de produção"
    install -m 600 -o "$APP_USER" -g "$APP_USER" "$BACKEND/.env.example" "$BACKEND/.env"
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
    definir DB_DATABASE sancta
    definir DB_USERNAME sancta
    definir DB_PASSWORD "$DB_SENHA"
    definir MAIL_FROM_ADDRESS "\"nao-responda@$DOMINIO\""
    definir SANCTA_ADMIN_PATH "gestao-$(openssl rand -hex 3)"
    definir SANCTA_ADMIN_EMAIL "$EMAIL_ADMIN"
    definir SANCTA_ADMIN_PASSWORD ""
    definir SANCTA_FAKE_PAYMENTS false
fi

#──────────────── Aplicação ────────────────
passo "Dependências do PHP (Composer)"
como_sancta composer install -d "$BACKEND" --no-dev --optimize-autoloader --no-interaction --no-progress

passo "Chave da aplicação e migrations"
grep -q '^APP_KEY=base64:' "$BACKEND/.env" || artisan key:generate --force
artisan migrate --force

if [[ ! -f "$BACKEND/storage/app/.dados-iniciais" ]]; then
    passo "Dados iniciais"
    aviso "ANOTE a senha do super administrador que aparece abaixo: ela é mostrada uma única vez."
    artisan db:seed --force
    como_sancta touch "$BACKEND/storage/app/.dados-iniciais"
fi
[[ -L "$BACKEND/public/storage" ]] || artisan storage:link

passo "Caches de produção"
artisan optimize
artisan filament:optimize

passo "PHP-FPM"
install -m 644 "$AQUI/php-fpm-sancta.conf" "/etc/php/$PHPV/fpm/pool.d/sancta.conf"
install -m 644 "$AQUI/php-sancta.ini" "/etc/php/$PHPV/fpm/conf.d/99-sancta.ini"
if [[ -f "/etc/php/$PHPV/fpm/pool.d/www.conf" ]]; then
    mv "/etc/php/$PHPV/fpm/pool.d/www.conf" "/etc/php/$PHPV/fpm/pool.d/www.conf.desativado"
fi
"php-fpm$PHPV" -t
systemctl restart "php$PHPV-fpm"

passo "Nginx"
cat > /etc/nginx/conf.d/sancta-geral.conf << 'EOF'
# Sancta: sem versão do Nginx nas respostas; compactação dos textos.
server_tokens off;
gzip_vary on;
gzip_types text/plain text/css text/xml application/javascript application/json application/xml application/manifest+json image/svg+xml;
EOF
if [[ -f /etc/nginx/sites-available/sancta ]]; then
    echo "Configuração do site já existe (pode ter o HTTPS do certbot); mantida como está."
else
    sed "s/__NOMES__/$DOMINIO/" "$AQUI/nginx-sancta.conf" > /etc/nginx/sites-available/sancta
    if [[ "${COM_WWW,,}" == s* ]]; then
        cat >> /etc/nginx/sites-available/sancta << EOF

server {
    listen 80;
    listen [::]:80;
    server_name www.$DOMINIO;
    return 301 https://$DOMINIO\$request_uri;
}
EOF
    fi
fi
ln -sf ../sites-available/sancta /etc/nginx/sites-enabled/sancta
rm -f /etc/nginx/sites-enabled/default
nginx -t -q
# Reinicia (e não só recarrega) para o Nginx assumir o grupo "sancta" recém-adicionado.
systemctl restart nginx

passo "Certificado HTTPS (Let's Encrypt)"
nomes=(-d "$DOMINIO")
if [[ "${COM_WWW,,}" == s* ]]; then nomes+=(-d "www.$DOMINIO"); fi
if [[ -d "/etc/letsencrypt/live/$DOMINIO" ]]; then
    echo "Certificado já emitido; a renovação é automática."
elif getent ahostsv4 "$DOMINIO" > /dev/null; then
    # Ao emitir, aceita os termos de uso do Let's Encrypt em nome de quem roda o instalador.
    if ! certbot --nginx "${nomes[@]}" --redirect --non-interactive --agree-tos -m "$EMAIL_CERTIFICADO"; then
        aviso "Não consegui emitir o certificado. Confira se o domínio aponta para o IP desta VPS e se as portas 80/443 estão liberadas na Oracle; depois rode: sudo certbot --nginx ${nomes[*]} --redirect"
    fi
else
    aviso "O domínio $DOMINIO ainda não aponta para esta VPS. Quando apontar, rode: sudo certbot --nginx ${nomes[*]} --redirect"
fi

passo "Agendador, atualização e backup"
# Os scripts que rodam como root ficam fora da pasta do código (que é do usuário sancta).
install -m 750 -o root -g root "$AQUI/atualizar.sh" /usr/local/sbin/sancta-atualizar
install -m 750 -o root -g root "$AQUI/backup.sh" /usr/local/sbin/sancta-backup
cat > /etc/cron.d/sancta << EOF
# Sancta: agendador do Laravel (a cada minuto) e backup diário às 04:15.
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * $APP_USER cd $BACKEND && php artisan schedule:run >> /dev/null 2>&1
15 4 * * * root /usr/local/sbin/sancta-backup >> /var/log/sancta-backup.log 2>&1
EOF
chmod 644 /etc/cron.d/sancta

#──────────────── Verificação ────────────────
passo "Verificação"
if [[ -d "/etc/letsencrypt/live/$DOMINIO" ]]; then
    base="https://$DOMINIO"
    resolver=(--resolve "$DOMINIO:443:127.0.0.1")
else
    base="http://$DOMINIO"
    resolver=(--resolve "$DOMINIO:80:127.0.0.1")
fi
site="$(curl -s -o /dev/null -w '%{http_code}' "${resolver[@]}" "$base/")"
api="$(curl -s -o /dev/null -w '%{http_code}' "${resolver[@]}" "$base/api/v1/config")"
echo "Site: HTTP $site · API: HTTP $api"
[[ "$site" == 200 && "$api" == 200 ]] || aviso "Esperava 200 nos dois. Veja $BACKEND/storage/logs e /var/log/nginx/error.log."

painel="$(grep '^SANCTA_ADMIN_PATH=' "$BACKEND/.env" | cut -d= -f2)"
printf '\n\033[1;32mSancta instalado.\033[0m\n'
echo "  Site:    https://$DOMINIO"
echo "  Painel:  https://$DOMINIO/$painel"
echo "  Login:   $EMAIL_ADMIN (senha mostrada na etapa \"Dados iniciais\")"
echo
echo "  Depois de enviar mudanças ao GitHub:  sudo sancta-atualizar"
echo "  Backups diários em /var/backups/sancta (copie para fora da VPS)."
echo "  Próximos passos no painel: 2FA no Perfil e Sistema → Integrações e segurança."
