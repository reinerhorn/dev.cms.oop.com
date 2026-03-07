#!/bin/bash

HOMEBREW_HTTPD="/opt/homebrew/bin/httpd"
HOMEBREW_CONF="/opt/homebrew/etc/httpd/httpd.conf"
LOG_DIR="/opt/homebrew/var/log/httpd"
SERVICE_NAME="httpd"

# macOS Notification Helper
notify() {
    osascript -e "display notification \"$1\" with title \"Apache Manager\""
}

# Status prüfen
check_status() {
    if pgrep -x "httpd" >/dev/null; then
        echo "✅ Apache (Homebrew) läuft."
        notify "Apache läuft."
    else
        echo "❌ Apache läuft nicht."
        notify "Apache läuft nicht!"
    fi
}

# Start
start_apache() {
    echo "📌 Starte Apache..."
    $HOMEBREW_HTTPD -k start -f $HOMEBREW_CONF
    notify "Apache wurde gestartet."
}

# Stop
stop_apache() {
    echo "📌 Stoppe Apache..."
    $HOMEBREW_HTTPD -k stop -f $HOMEBREW_CONF
    notify "Apache wurde gestoppt."
}

# Neustart
restart_apache() {
    echo "📌 Starte Apache neu..."
    $HOMEBREW_HTTPD -k restart -f $HOMEBREW_CONF
    notify "Apache wurde neu gestartet."
}

# Logs anzeigen
show_logs() {
    if [ -f "$LOG_DIR/error_log" ]; then
        echo "📄 Letzte 20 Zeilen aus dem Error-Log:"
        tail -n 20 "$LOG_DIR/error_log"
    else
        echo "❌ Keine Logs gefunden."
    fi
}

# Menü
while true; do
    echo ""
    echo "============================"
    echo " Apache Manager (Homebrew)"
    echo "============================"
    echo "1) Status anzeigen"
    echo "2) Apache starten"
    echo "3) Apache stoppen"
    echo "4) Apache neu starten"
    echo "5) Logs anzeigen"
    echo "6) Exit"
    echo "============================"
    read -p "Wähle eine Option: " option

    case $option in
        1) check_status ;;
        2) start_apache ;;
        3) stop_apache ;;
        4) restart_apache ;;
        5) show_logs ;;
        6) echo "Beende Script..."; exit ;;
        *) echo "❌ Ungültige Eingabe" ;;
    esac
done
