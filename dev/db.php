<?php
function getDbConnection() {
    static $connection = null;

    if ($connection === null) {
        $config = require '/private/conf/cms_igsh_config.php';

        $connection = new mysqli(
            $config['db_host'],
            $config['db_user'],
            $config['db_pass'],
            $config['db_name']
        );

        if ($connection->connect_error) {
            error_log("Datenbankverbindungsfehler: " . $connection->connect_error);
            die("<h1>Datenbank nicht erreichbar</h1>");
        }

        $connection->set_charset('utf8mb4');
    }

    return $connection;
}

function loadAppConfig() {
    $db = getDbConnection();
    $config = [];

    $result = $db->query("SELECT schluessel, wert FROM konfiguration");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $config[$row['schluessel']] = $row['wert'];
        }
    }

    return $config;
}
