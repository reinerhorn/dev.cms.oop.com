#!/bin/bash

# Aktuelle Konfiguration und Backup-Ordner
CURRENT_CONF="/opt/homebrew/etc/httpd/httpd.conf"
BACKUP_DIR="$HOME/Backups"

# Letztes Backup ermitteln
LATEST_BACKUP=$(ls -1t "$BACKUP_DIR"/httpd.conf.* 2>/dev/null | head -n 1)

if [ ! -f "$LATEST_BACKUP" ]; then
  echo "❌ Kein Backup gefunden unter $BACKUP_DIR"
  exit 1
fi

echo "📁 Vergleiche:"
echo "🔹 Aktuell: $CURRENT_CONF"
echo "🔸 Backup : $LATEST_BACKUP"
echo

# Farbiger Vergleich (falls colordiff installiert ist)
if command -v colordiff &> /dev/null; then
  colordiff -u "$LATEST_BACKUP" "$CURRENT_CONF"
else
  diff -u "$LATEST_BACKUP" "$CURRENT_CONF"
  echo "ℹ️  Tipp: 'colordiff' installieren für farbige Ausgabe → brew install colordiff"
fi
