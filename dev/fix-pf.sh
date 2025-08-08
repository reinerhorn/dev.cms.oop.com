#!/bin/bash

BACKUP="/etc/pf.conf.backup.$(date +%F-%H%M)"
FIXED="/tmp/pf.conf.fixed"

echo "🔐 Backup der aktuellen pf.conf nach $BACKUP..."
sudo cp /etc/pf.conf "$BACKUP"

echo "✍️  Erstelle korrigierte pf.conf..."

sudo tee "$FIXED" >/dev/null <<'EOF'
# Optionen
set skip on lo0

# Normalisierung
scrub in all

# Weiterleitungen (rdr)
rdr pass on lo0 inet proto tcp from any to any port 80  -> 127.0.0.1 port 8080
rdr pass on lo0 inet proto tcp from any to any port 443 -> 127.0.0.1 port 8443

# Filterregeln
pass in all
pass out all
EOF

echo "📦 Überschreibe /etc/pf.conf mit korrekter Version..."
sudo cp "$FIXED" /etc/pf.conf

echo "🔄 Lade neue Regeln..."
sudo pfctl -f /etc/pf.conf
sudo pfctl -e

echo "✅ Fertig. Aktive Portweiterleitung:"
sudo pfctl -sr | grep rdr
