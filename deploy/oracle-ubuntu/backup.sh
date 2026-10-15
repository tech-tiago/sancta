#!/usr/bin/env bash
# Sancta — backup diário do banco, dos arquivos enviados pelo painel e do .env (com a APP_KEY,
# sem a qual as credenciais salvas no painel não podem ser lidas).
# Instalado pelo instalar.sh como /usr/local/sbin/sancta-backup; o cron roda às 04:15.
# Guarda 14 dias em /var/backups/sancta, legível pelo grupo adm (o usuário ubuntu pode copiar).
set -Eeuo pipefail
umask 027

BACKEND=/var/www/sancta/backend
DESTINO=/var/backups/sancta
DIAS=14
data="$(date +%F)"

install -d -m 750 -o root -g adm "$DESTINO"

mysqldump --single-transaction --quick --routines --triggers --no-tablespaces sancta \
    | gzip > "$DESTINO/banco-$data.sql.gz"
tar -czf "$DESTINO/arquivos-$data.tar.gz" -C "$BACKEND" --exclude=storage/app/livewire-tmp storage/app .env
chgrp adm "$DESTINO/banco-$data.sql.gz" "$DESTINO/arquivos-$data.tar.gz"

find "$DESTINO" -type f -name '*.gz' -mtime +"$DIAS" -delete
echo "$(date '+%F %T') backup ok: $(du -sh "$DESTINO" | cut -f1) em $DESTINO"
