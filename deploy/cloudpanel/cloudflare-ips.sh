#!/usr/bin/env bash
# Mantém a lista de IPs da Cloudflare usada pela trava "só a Cloudflare acessa o site".
# Instalado pelo instalar-cloudpanel.sh como /usr/local/sbin/cloudflare-ips; o cron roda toda
# segunda-feira às 05:20. Vale para o Sancta e para qualquer outro site atrás da Cloudflare.
#
#   sudo cloudflare-ips              baixa a lista oficial, valida e recarrega o Nginx
#   sudo cloudflare-ips --desligar   libera os sites para qualquer IP (Cloudflare em "nuvem cinza")
#
# Gera:
#   /etc/nginx/cloudflare/allow.conf  "allow <faixa>;" + "deny all;"; um site usa com
#                                     "include /etc/nginx/cloudflare/allow.conf;" no vhost
#   /etc/nginx/cloudflare/ips.txt     a mesma lista, uma faixa por linha
# e copia a lista para cada destino de /etc/cloudflare-ips/copias.conf ("caminho dono" por linha),
# como o storage/app/cloudflare-ips.txt do Sancta (TRUSTED_PROXIES=cloudflare).
set -Eeuo pipefail
umask 022

PASTA=/etc/nginx/cloudflare
COPIAS=/etc/cloudflare-ips/copias.conf

[[ $EUID -eq 0 ]] || { echo "Rode com sudo." >&2; exit 1; }
install -d -m 755 "$PASTA"

aplicar() { # aplicar <arquivo novo>: troca o allow.conf só se o Nginx aceitar
    local novo=$1
    if [[ -f "$PASTA/allow.conf" ]]; then cp "$PASTA/allow.conf" "$PASTA/allow.conf.anterior"; fi
    install -m 644 "$novo" "$PASTA/allow.conf"
    if ! nginx -t -q 2> /dev/null; then
        if [[ -f "$PASTA/allow.conf.anterior" ]]; then mv "$PASTA/allow.conf.anterior" "$PASTA/allow.conf"; fi
        echo "O Nginx recusou a configuração nova; mantida a anterior." >&2
        exit 1
    fi
    systemctl reload nginx
}

if [[ "${1:-}" == "--desligar" ]]; then
    tmp="$(mktemp)"
    printf '# Trava da Cloudflare DESLIGADA em %s (cloudflare-ips --desligar).\n# Para religar: sudo cloudflare-ips\nallow all;\n' "$(date '+%F %T')" > "$tmp"
    aplicar "$tmp"
    rm -f "$tmp"
    echo "Trava desligada: os sites aceitam qualquer IP. Religue com: sudo cloudflare-ips"
    exit 0
fi

v4="$(curl -fsS --max-time 20 --retry 3 https://www.cloudflare.com/ips-v4)"
v6="$(curl -fsS --max-time 20 --retry 3 https://www.cloudflare.com/ips-v6)"

# Só aceita faixas bem formadas: nada que vá para o Nginx pode trazer espaço, ";" ou diretiva.
faixas=()
while read -r linha; do
    [[ -z "$linha" ]] && continue
    if [[ "$linha" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}/[0-9]{1,2}$ || "$linha" =~ ^[0-9a-f:]+/[0-9]{1,3}$ ]]; then
        faixas+=("$linha")
    else
        echo "Linha inesperada na lista da Cloudflare: '$linha'. Nada foi alterado." >&2
        exit 1
    fi
done <<< "$v4"$'\n'"$v6"
(( ${#faixas[@]} >= 10 )) || { echo "Lista da Cloudflare curta demais (${#faixas[@]}). Nada foi alterado." >&2; exit 1; }

tmp="$(mktemp)"
{
    echo "# Gerado por cloudflare-ips em $(date '+%F %T'). Não edite: é refeito toda semana."
    echo "allow 127.0.0.1;"
    echo "allow ::1;"
    printf 'allow %s;\n' "${faixas[@]}"
    echo "deny all;"
} > "$tmp"
# Compara sem a linha da data: só recarrega o Nginx quando as faixas mudam (ou a trava estava desligada).
if ! diff -q <(tail -n +2 "$tmp") <(tail -n +2 "$PASTA/allow.conf" 2> /dev/null) > /dev/null; then
    aplicar "$tmp"
fi
rm -f "$tmp"
printf '%s\n' "${faixas[@]}" > "$PASTA/ips.txt"
chmod 644 "$PASTA/ips.txt"

if [[ -f "$COPIAS" ]]; then
    while read -r destino dono; do
        [[ -z "${destino:-}" || "$destino" == \#* ]] && continue
        [[ -d "$(dirname "$destino")" ]] || continue
        install -m 644 -o "$dono" -g "$(id -gn "$dono")" "$PASTA/ips.txt" "$destino"
    done < "$COPIAS"
fi

echo "$(date '+%F %T') Cloudflare: ${#faixas[@]} faixas aplicadas."
