#!/bin/bash

DOMAIN="dev.hd-staffingservices.de"

echo "🧹 Versuche, HTTPS-Zwang (HSTS) für $DOMAIN zu entfernen..."

# Für Chrome/Chromium
echo "🧽 Lösche HSTS für Chrome/Chromium (über Net-Internals empfohlen)..."
echo "  ➤ Bitte manuell aufrufen: chrome://net-internals/#hsts"

# Für Firefox
echo "🔍 Suche Firefox-Profile..."
for PROFILE in "$HOME/Library/Application Support/Firefox/Profiles/"*/; do
  echo "  ➤ Bearbeite Profil: $PROFILE"
  SQLITE_DB="$PROFILE/SiteSecurityServiceState.txt"

  if [ -f "$SQLITE_DB" ]; then
    grep -v "$DOMAIN" "$SQLITE_DB" > "$SQLITE_DB.tmp" && mv "$SQLITE_DB.tmp" "$SQLITE_DB"
    echo "    ✔️  HSTS-Eintrag für $DOMAIN aus Firefox entfernt (Textdatenbank)."
  else
    echo "    ⚠️  Kein HSTS-Eintrag gefunden in $PROFILE"
  fi
done

# Safari (löscht alle Caches komplett, optional!)
read -p "❓ Safari-Cache vollständig löschen? [j/N] " SAFARI_CONFIRM
if [[ "$SAFARI_CONFIRM" =~ ^[Jj]$ ]]; then
  echo "🗑️  Lösche Safari-Cache (System, Cookies, HSTS)..."
  rm -rf "$HOME/Library/Caches/com.apple.Safari"
  rm -rf "$HOME/Library/Safari/Databases"
  rm -rf "$HOME/Library/Safari/Favicon Cache"
  echo "    ✔️  Safari-Caches gelöscht. Safari neu starten!"
else
  echo "    ⏭️  Safari-Cache wurde übersprungen."
fi

echo "✅ HSTS-Clearing abgeschlossen. Bitte Browser neu starten!"
