#!/bin/bash

# ============================
# macOS Apache-Setup für Homebrew
# ============================

HOMEBREW_HTTPD="/opt/homebrew/bin/httpd"
HOMEBREW_CONF="/opt/homebrew/etc/httpd/httpd.conf"

echo "📌 Deaktiviere den internen macOS Apache..."
sudo launchctl disable system/org.apache.httpd 2>/dev/null
sudo launchctl bootout system /System/Library/LaunchDaemons/org.apache.httpd.plist 2>/dev/null

echo "✅ Interner Apache deaktiviert."

echo "📌 Starte Homebrew Apache..."
# Homebrew Apache neu starten
$HOMEBREW_HTTPD -k stop -f $HOMEBREW_CONF 2>/dev/null
$HOMEBREW_HTTPD -k start -f $HOMEBREW_CONF

echo "✅ Homebrew Apache gestartet."

echo "📌 Prüfe, ob Apache auf Port 80 läuft..."
if lsof -nP -iTCP:80 -sTCP:LISTEN | grep "$HOMEBREW_HTTPD" > /dev/null; then
    echo "✅ Homebrew Apache läuft auf Port 80."
else
    echo "❌ Homebrew Apache läuft NICHT auf Port 80!"
fi

echo "📌 Richte Homebrew Apache für Autostart ein..."
brew services restart httpd

echo "✅ Setup abgeschlossen. Homebrew-Apache ist aktiv."
