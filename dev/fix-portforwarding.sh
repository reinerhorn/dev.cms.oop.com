#!/bin/bash

ANCHOR_DIR="/etc/pf.anchors"
ANCHOR_NAME="myredir"
ANCHOR_FILE="$ANCHOR_DIR/$ANCHOR_NAME"
PF_CONF="/etc/pf.conf"

# 🔐 Regel-Inhalt
RDR_RULES="rdr pass on lo0 inet proto tcp from any to any port 80  -> 127.0.0.1 port 8080
rdr pass on lo0 inet proto tcp from any to any port 443 -> 127.0.0.1 port 8443"

echo "🔧 Erstelle oder aktualisiere Anchor-Datei unter $ANCHOR_FILE..."
sudo mkdir -p "$ANCHOR_DIR"
echo "$RDR_RULES" | sudo tee "$ANCHOR_FILE" > /dev/null

# 🔍 Prüfen ob anchor in /etc/pf.conf bereits vorhanden ist
if ! grep -q 'anchor "myredir"' "$PF_CONF"; then
    echo "🧷 Trage anchor in /etc/pf.conf ein..."
    sudo sed -i.bak '/^scrub in all$/a\
anchor "myredir"\
load anchor "myredir" from "/etc/pf.anchors/myredir"
' "$PF_CONF"
else
    echo "✅ Anchor ist bereits in /etc/pf.conf eingetragen."
fi

# 🔄 pf neu laden
echo "♻️ Lade pf-Regeln neu..."
sudo pfctl -F all
sudo pfctl -f "$PF_CONF"
sudo pfctl -e

# ✅ Ausgabe prüfen
echo "📋 Aktive Weiterleitungen:"
sudo pfctl -sr | grep rdr
