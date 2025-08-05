<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 function getDbConnection() {
    static $connection = null;

    if ($connection === null) {
        $config = require '/private/conf/cms_oop-config.php';

        $connection = new mysqli(
            $config['db_host'],
            $config['db_user'],
            $config['db_pass'],
            $config['db_name']
        );

        if ($connection->connect_error) {
            die("Verbindung fehlgeschlagen: " . $connection->connect_error);
        }
    }

    return $connection;
}
 


if (!class_exists('CMSApp', false)) {
    class CMSApp {
        private static $db = null;

        public static function getDb() {
            if (self::$db === null) {
                self::$db = getDbConnection();
            }
            return self::$db;
        }
    }
}