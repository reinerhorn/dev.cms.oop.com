<?php

function getDbConnection() {
    static $connection = null;

    if ($connection === null) {
        $config = require_once '/private/conf/h-d_config.php'; // Absoluter Pfad  
        $connection = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

        if ($connection->connect_error) {
            error_log("Datenbankverbindungsfehler: " . $connection->connect_error);
            die("<h1>Datenbank nicht erreichbar</h1>");
        }
    }
    return $connection;
}

?>