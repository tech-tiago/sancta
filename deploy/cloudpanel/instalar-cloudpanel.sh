#!/usr/bin/env bash
# Instalação segura do CloudPanel numa VPS da Oracle Cloud com Ubuntu 24.04 (x86 ou ARM/Ampere).
# Serve para o Sancta e para os próximos sites do CloudPanel.
#
# Uso, na VPS, dentro da pasta copiada do repositório (deploy/cloudpanel):
#   sudo bash instalar-cloudpanel.sh
#
# O que faz:
#   1. Atualiza o sistema e acerta fuso horário e hostname.
#   2. Baixa o instalador oficial do CloudPanel e só executa se o SHA-256 bater com o publicado.
#   3. Firewall: o UFW do CloudPanel passa a mandar nas portas; as regras de bloqueio que a imagem
#      da Oracle traz no iptables (que deixariam 80/443 fechadas) saem, e a cadeia InstanceServices
#      da Oracle (proteção do serviço de metadados) fica intacta.
#   4. Cria o administrador do CloudPanel pela linha de comando, com senha sorteada: a tela de
#      "primeiro acesso" nunca fica aberta para robôs.
#   5. SSH só com chave e sem root, fail2ban, atualizações automáticas de segurança, e-mail
#      (Postfix), MySQL e Redis ouvindo só dentro da máquina.
#   6. Instala o cloudflare-ips (lista de IPs da Cloudflare atualizada toda semana).
#
# A porta 8443 (painel) NÃO deve ser aberta na Oracle: o acesso é por túnel SSH (ver o guia).
# Pode ser executado de novo: cada etapa pula o que já está feito.
#
# Respostas também por variáveis de ambiente: CLP_USUARIO, CLP_EMAIL, CLP_NOME, CLP_SOBRENOME.
# Quando o CloudPanel publicar um instalador novo, pegue o SHA-256 na página oficial
# (https://www.cloudpanel.io/docs/v2/getting-started/oracle-cloud/installation/installer/) e rode:
#   sudo CLOUDPANEL_SHA256=<novo> bash instalar-cloudpanel.sh
set -Eeuo pipefail

CLOUDPANEL_URL=https://installer.cloudpanel.io/ce/v2/install.sh
CLOUDPANEL_SHA256="${CLOUDPANEL_SHA256:-8146dbe0a488e7088b04071b0c34d59aa0ab1fe9dcec382d395fd155c9e6c476}"
FUSO=America/Sao_Paulo
AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

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

#──────────────── Conferências ────────────────
[[ $EUID -eq 0 ]] || falha "Rode com sudo: sudo bash instalar-cloudpanel.sh"
[[ -f "$AQUI/cloudflare-ips.sh" ]] || falha "cloudflare-ips.sh não está ao lado deste script. Copie a pasta cloudpanel inteira."
. /etc/os-release
[[ "${ID:-}" == ubuntu ]] || falha "Este instalador é para Ubuntu (encontrado: ${PRETTY_NAME:-desconhecido})."
case "${VERSION_ID:-}" in
    24.04) DB_ENGINE="${DB_ENGINE:-MYSQL_8.4}" ;;
    22.04)
        DB_ENGINE="${DB_ENGINE:-MYSQL_8.0}"
        aviso "Ubuntu 22.04: o CloudPanel só oferece MySQL 8.0 nele. Prefira criar a VPS com Ubuntu 24.04."
        ;;
    *) falha "Use Ubuntu 24.04 (ou 22.04). Esta máquina tem o ${VERSION_ID:-?}." ;;
esac
USUARIO_SSH="${SUDO_USER:-ubuntu}"
JA_INSTALADO=false
command -v clpctl > /dev/null && JA_INSTALADO=true

pergunta CLP_USUARIO "Usuário do administrador do CloudPanel (ex.: tiago)"
[[ "$CLP_USUARIO" =~ ^[a-z][a-z0-9._-]{2,31}$ ]] || falha "Use de 3 a 32 letras minúsculas, números, ponto, hífen ou sublinhado."
pergunta CLP_EMAIL "E-mail do administrador do CloudPanel"
[[ "$CLP_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || falha "E-mail inválido: $CLP_EMAIL"
pergunta CLP_NOME "Nome"
pergunta CLP_SOBRENOME "Sobrenome"

#──────────────── Sistema ────────────────
passo "Atualizando o sistema"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get -y -q -o Dpkg::Options::=--force-confold upgrade
apt-get install -y -q curl wget sudo cron ca-certificates fail2ban unattended-upgrades

passo "Fuso horário ($FUSO) e hostname"
# O CloudPanel usa o fuso da máquina para a hora dos backups.
timedatectl set-timezone "$FUSO"
if ! getent hosts "$(hostname)" > /dev/null; then
    echo "127.0.1.1 $(hostname)" >> /etc/hosts
fi

#──────────────── CloudPanel ────────────────
if [[ "$JA_INSTALADO" == true ]]; then
    passo "CloudPanel já instalado: pulando a instalação"
else
    passo "Baixando e conferindo o instalador oficial do CloudPanel"
    pasta_tmp="$(mktemp -d)"
    curl -fsS "$CLOUDPANEL_URL" -o "$pasta_tmp/install.sh"
    if ! echo "$CLOUDPANEL_SHA256  $pasta_tmp/install.sh" | sha256sum -c --quiet -; then
        rm -rf "$pasta_tmp"
        falha "O SHA-256 do instalador não confere com o esperado. Se o CloudPanel publicou versão nova, pegue o SHA-256 na página oficial e rode: sudo CLOUDPANEL_SHA256=<novo> bash instalar-cloudpanel.sh"
    fi
    ok "Instalador conferido (SHA-256 $CLOUDPANEL_SHA256)."

    passo "Instalando o CloudPanel (banco $DB_ENGINE). Leva alguns minutos."
    (cd "$pasta_tmp" && CLOUD=oci DB_ENGINE="$DB_ENGINE" bash install.sh)
    rm -rf "$pasta_tmp"
    command -v clpctl > /dev/null || falha "O CloudPanel não terminou de instalar. Veja as mensagens acima."
fi

#──────────────── Firewall ────────────────
passo "Firewall: UFW do CloudPanel no comando"
if ! ufw status | grep -q "Status: active"; then
    aviso "O UFW não estava ativo; ativando com SSH, HTTP e HTTPS liberados."
    ufw default deny incoming > /dev/null
    ufw default allow outgoing > /dev/null
    ufw allow 22/tcp > /dev/null
    ufw allow 80/tcp > /dev/null
    ufw allow 443/tcp > /dev/null
    ufw --force enable > /dev/null
fi
ufw status | grep -qE '^(22|22/tcp|OpenSSH)[[:space:]]+ALLOW' || falha "O UFW não libera a porta 22; não mexi no iptables da Oracle para não te trancar fora."

# A imagem da Oracle termina a cadeia INPUT com um REJECT que vem antes das regras do UFW: sem
# tirá-lo, as portas 80/443 continuam fechadas mesmo liberadas no CloudPanel. O ACCEPT da porta 22
# também sai, para que restringir o SSH pelo firewall do CloudPanel funcione de verdade.
regras=/etc/iptables/rules.v4
if [[ -f "$regras" ]] && grep -qE -- '^-A (INPUT|FORWARD) -j REJECT|^-A INPUT .*--dport 22 -j ACCEPT' "$regras"; then
    [[ -f "$regras.oracle-original" ]] || cp "$regras" "$regras.oracle-original"
    sed -i -E '/^-A (INPUT|FORWARD) -j REJECT/d; /^-A INPUT .*--dport 22 -j ACCEPT/d' "$regras"
    ok "Regras de bloqueio da Oracle retiradas de $regras (original em $regras.oracle-original)."
fi
while read -r regra; do
    # shellcheck disable=SC2086 # a regra precisa ser dividida em argumentos
    iptables ${regra/-A /-D }
done < <(iptables -S INPUT | grep -E -- '-j REJECT|--dport 22 -j ACCEPT' | grep -v -- '-j ufw' || true)
while read -r regra; do
    # shellcheck disable=SC2086
    iptables ${regra/-A /-D }
done < <(iptables -S FORWARD | grep -E -- '-j REJECT' | grep -v -- '-j ufw' || true)
if iptables -S OUTPUT | grep -q InstanceServices || ! iptables -S | grep -q -- '-N InstanceServices'; then
    ok "Cadeia InstanceServices da Oracle preservada."
else
    iptables -I OUTPUT -d 169.254.0.0/16 -j InstanceServices
    ok "Ligação da cadeia InstanceServices da Oracle restaurada."
fi

#──────────────── Administrador do CloudPanel ────────────────
passo "Administrador do CloudPanel"
if clpctl user:list 2> /dev/null | grep -qwF -- "$CLP_USUARIO"; then
    echo "O usuário $CLP_USUARIO já existe."
else
    CLP_SENHA="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)"
    clpctl user:add --userName="$CLP_USUARIO" --email="$CLP_EMAIL" --firstName="$CLP_NOME" \
        --lastName="$CLP_SOBRENOME" --password="$CLP_SENHA" --role='admin' --timezone="$FUSO" --status='1' > /dev/null
    printf '\n\033[1;33m  ANOTE AGORA a senha do CloudPanel (%s): %s\033[0m\n' "$CLP_USUARIO" "$CLP_SENHA"
    echo "  Ela não fica salva em lugar nenhum. No primeiro acesso, ative o 2FA no seu perfil."
    read -r -p "  Tecle Enter depois de anotar... "
fi

#──────────────── Endurecimento ────────────────
passo "SSH: só com chave e nunca como root"
casa_ssh="$(getent passwd "$USUARIO_SSH" | cut -d: -f6)"
if [[ -s "$casa_ssh/.ssh/authorized_keys" ]]; then
    cat > /etc/ssh/sshd_config.d/10-seguranca.conf << 'EOF'
# Acesso só por chave e nunca como root (instalar-cloudpanel.sh).
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
EOF
    sshd -t
    systemctl try-reload-or-restart ssh
else
    aviso "Não encontrei chave SSH para $USUARIO_SSH; deixei o SSH como estava."
fi

passo "fail2ban e atualizações automáticas de segurança"
cat > /etc/apt/apt.conf.d/20auto-upgrades << 'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF
systemctl enable --now fail2ban unattended-upgrades > /dev/null 2>&1

passo "Serviços internos ouvindo só dentro da máquina"
if command -v postconf > /dev/null && [[ "$(postconf -h inet_interfaces)" != loopback-only ]]; then
    # O Postfix do CloudPanel serve só para e-mails do sistema; o Sancta envia pelo SMTP do painel.
    postconf -e 'inet_interfaces = loopback-only'
    systemctl restart postfix
fi
publico() { ss -ltnH "( sport = :$1 )" | awk '{print $4}' | grep -vE '^(127\.|\[::1\]|::1)' || true; }
if [[ -n "$(publico 3306)" ]]; then
    install -d /etc/mysql/conf.d
    printf '[mysqld]\nbind-address = 127.0.0.1\nloose-mysqlx-bind-address = 127.0.0.1\n' > /etc/mysql/conf.d/zz-somente-local.cnf
    if ! systemctl restart mysql; then
        rm -f /etc/mysql/conf.d/zz-somente-local.cnf
        systemctl restart mysql
        aviso "O MySQL não aceitou a restrição a 127.0.0.1; desfeito. Ele continua protegido pelo UFW e pela Oracle."
    fi
fi
for porta in 3306 6379 25; do
    if [[ -n "$(publico $porta)" ]]; then
        aviso "A porta $porta ainda ouve fora da máquina ($(publico $porta | xargs)); o UFW e a Oracle a bloqueiam."
    else
        ok "Porta $porta só dentro da máquina."
    fi
done

passo "Lista de IPs da Cloudflare (atualização semanal)"
install -m 750 -o root -g root "$AQUI/cloudflare-ips.sh" /usr/local/sbin/cloudflare-ips
install -d -m 755 /etc/cloudflare-ips
[[ -f /etc/cloudflare-ips/copias.conf ]] || echo "# caminho dono  (cópias da lista de IPs da Cloudflare para os sites)" > /etc/cloudflare-ips/copias.conf
cat > /etc/cron.d/cloudflare-ips << 'EOF'
# Atualiza a lista de IPs da Cloudflare toda segunda-feira às 05:20.
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
20 5 * * 1 root /usr/local/sbin/cloudflare-ips >> /var/log/cloudflare-ips.log 2>&1
EOF
chmod 644 /etc/cron.d/cloudflare-ips
/usr/local/sbin/cloudflare-ips

#──────────────── Resumo ────────────────
printf '\n\033[1;32mCloudPanel instalado e protegido.\033[0m\n'
echo
echo "  Na Oracle (Security List da VCN), deixe abertas só as portas 22, 80 e 443. NÃO abra a 8443."
echo
echo "  Para entrar no CloudPanel, no seu computador:"
echo "    ssh -i C:\\caminho\\chave-oracle.key -N -L 8443:127.0.0.1:8443 $USUARIO_SSH@IP-DA-VPS"
echo "  e abra https://localhost:8443 (aceite o aviso do certificado). Login: $CLP_USUARIO"
echo
echo "  No CloudPanel: ative o 2FA no seu perfil e configure Admin Area → Backups (cópia fora da VPS)."
echo "  Depois, com o domínio na Cloudflare: sudo bash instalar-sancta.sh"
echo
read -r -p "A Oracle recomenda reiniciar depois da instalação. Reiniciar agora? (s/n) [s]: " reiniciar
if [[ "${reiniciar:-s}" == s* ]]; then
    reboot
fi
