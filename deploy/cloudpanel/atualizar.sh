#!/usr/bin/env bash
# Sancta — publica no CloudPanel a versão mais recente do branch main do GitHub.
# Instalado pelo instalar-sancta.sh como /usr/local/sbin/sancta-atualizar. Uso:
#   sudo sancta-atualizar            atualiza se houver versão nova
#   sudo sancta-atualizar --forcar   refaz tudo mesmo sem versão nova
set -Eeuo pipefail

[[ $EUID -eq 0 ]] || { echo "Rode com sudo: sudo sancta-atualizar" >&2; exit 1; }
[[ -f /etc/sancta/sancta.conf ]] || { echo "Falta /etc/sancta/sancta.conf: rode o instalar-sancta.sh." >&2; exit 1; }
# shellcheck source=/dev/null
. /etc/sancta/sancta.conf # DOMINIO, SITE_USUARIO, PHPV, APP_DIR

BACKEND=$APP_DIR/backend
PHP="/usr/bin/php$PHPV"
COMPOSER="$(command -v composer)"

passo() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
como_site() { (cd / && sudo -u "$SITE_USUARIO" -H "$@"); }
artisan() { como_site "$PHP" "$BACKEND/artisan" "$@"; }

passo "Buscando a versão nova no GitHub"
como_site git -C "$APP_DIR" fetch --quiet origin main
antes="$(como_site git -C "$APP_DIR" rev-parse --short HEAD)"
depois="$(como_site git -C "$APP_DIR" rev-parse --short origin/main)"
if [[ "$antes" == "$depois" && "${1:-}" != "--forcar" ]]; then
    echo "Já está na versão mais recente ($antes). Para refazer mesmo assim: sudo sancta-atualizar --forcar"
    exit 0
fi
como_site git -C "$APP_DIR" log --oneline "$antes..$depois" || true

trap 'printf "\n\033[1;31m[x] A atualização parou na linha %s. O site continua em manutenção.\033[0m\n" "$LINENO"
      echo "    Corrija e rode de novo: sudo sancta-atualizar --forcar"
      echo "    Para tirar da manutenção sem terminar a atualização: sudo -u $SITE_USUARIO $PHP $BACKEND/artisan up"' ERR

passo "Site em manutenção"
artisan down --retry=30

passo "Código e dependências"
como_site git -C "$APP_DIR" merge --ff-only --quiet origin/main
como_site "$PHP" "$COMPOSER" install -d "$BACKEND" --no-dev --optimize-autoloader --no-interaction --no-progress

passo "Banco e caches"
artisan migrate --force
artisan optimize
artisan filament:optimize
systemctl reload "php$PHPV-fpm"

artisan up
trap - ERR
printf '\n\033[1;32mAtualizado: %s → %s\033[0m\n' "$antes" "$depois"

# Variáveis novas não entram sozinhas no .env de produção.
chaves() { grep -oE '^[A-Z0-9_]+=' "$1" | tr -d '=' | sort -u; }
novas="$(comm -23 <(chaves "$BACKEND/.env.example") <(chaves "$BACKEND/.env") | xargs)"
if [[ -n "$novas" ]]; then
    echo "Aviso: o .env.example tem variáveis que o .env ainda não tem: $novas"
fi
