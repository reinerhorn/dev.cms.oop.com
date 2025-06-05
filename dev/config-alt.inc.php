<?php
// Diese Funktion stellt die Verbindung zur Datenbank her
function getDbConnection() {
    // Lade die Konfiguration aus der Konfigurationsdatei
    $config = include('/private/conf/hd_config.php');  // Passe den Pfad an, falls notwendig

    // Versuche, eine Verbindung zur MySQL-Datenbank herzustellen
    $mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
    
    // Überprüfe, ob es beim Verbindungsaufbau Fehler gab
    if ($mysqli->connect_error) {
        // Falls ein Fehler aufgetreten ist, wird die Fehlermeldung angezeigt und das Script gestoppt
        die("Datenbankverbindung fehlgeschlagen: " . $mysqli->connect_error);
    }

    // Wenn keine Fehler aufgetreten sind, gebe das mysqli-Objekt zurück
    return $mysqli;
}

 
?>