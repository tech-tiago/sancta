#!/usr/bin/env bash
# Sancta — publica na VPS a versão mais recente do branch main do GitHub.
# Instalado pelo instalar.sh como /usr/local/sbin/sancta-atualizar. Uso:
#   sudo sancta-atualizar            atualiza se houver versão nova
#   sudo sancta-atualizar --forcar   refaz tudo mesmo sem versão nova
set -Eeuo pipefail

PHPV=8.3
APP_USER=sancta
APP_DIR=/var/www/sancta
BACKEND=$APP_DIR/backend

passo() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
como_sancta() { (cd / && sudo -u "$APP_USER" -H "$@"); }
artisan() { como_sancta php "$BACKEND/artisan" "$@"; }

[[ $EUID -eq 0 ]] || { echo "Rode com sudo: sudo sancta-atualizar" >&2; exit 1; }

passo "Buscando a versão nova no GitHub"
como_sancta git -C "$APP_DIR" fetch --quiet origin main
antes="$(como_sancta git -C "$APP_DIR" rev-parse --short HEAD)"
depois="$(como_sancta git -C "$APP_DIR" rev-parse --short origin/main)"
if [[ "$antes" == "$depois" && "${1:-}" != "--forcar" ]]; then
    echo "Já está na versão mais recente ($antes). Para refazer mesmo assim: sudo sancta-atualizar --forcar"
    exit 0
fi
como_sancta git -C "$APP_DIR" log --oneline "$antes..$depois" || true

trap 'printf "\n\033[1;31m[x] A atualização parou na linha %s. O site continua em manutenção.\033[0m\n" "$LINENO"
      echo "    Corrija e rode de novo: sudo sancta-atualizar --forcar"
      echo "    Para tirar da manutenção sem terminar a atualização: sudo -u $APP_USER php $BACKEND/artisan up"' ERR

passo "Site em manutenção"
artisan down --retry=30

passo "Código e dependências"
como_sancta git -C "$APP_DIR" merge --ff-only --quiet origin/main
como_sancta composer install -d "$BACKEND" --no-dev --optimize-autoloader --no-interaction --no-progress

passo "Banco e caches"
artisan migrate --force
artisan optimize
artisan filament:optimize
# O OPcache não relê os arquivos sozinho (validate_timestamps = 0): recarregar o PHP-FPM é obrigatório.
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
