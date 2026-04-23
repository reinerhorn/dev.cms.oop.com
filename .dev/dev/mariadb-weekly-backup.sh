#!/bin/bash

# Zielordner für Backups
BACKUP_DIR="$HOME/db_backups"
DATE=$(date +%Y-%m-%d)
FILENAME="mariadb-backup-$DATE.sql.gz"

mkdir -p "$BACKUP_DIR"

# Backup aller Datenbanken, komprimiert
/opt/homebrew/bin/mysqldump --all-databases --single-transaction --quick --lock-tables=false | gzip > "$BACKUP_DIR/$FILENAME"

# Optional: Alte Backups löschen, die älter als 60 Tage sind
find "$BACKUP_DIR" -name "mariadb-backup-*.sql.gz" -type f -mtime +60 -delete
