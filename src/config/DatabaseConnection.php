<?php
declare(strict_types=1);

namespace CMS\Config;

class DatabaseConnection {
    private static ?\mysqli $connection = null;

    public static function getConnection(): \mysqli {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $configPath = getenv('CMS_DB_CONFIG');
        if ($configPath === false || $configPath === '') {
            throw new \Exception("Die Umgebungsvariable CMS_DB_CONFIG ist nicht gesetzt.");
        }

        if (!file_exists($configPath)) {
            throw new \Exception("Datenbank-Konfigurationsdatei nicht gefunden: " . $configPath);
        }

        $config = require $configPath;
        if (!is_array($config)) {
            throw new \Exception("Datenbank-Konfigurationsdatei ist ungültig: " . $configPath);
        }

        if (self::$connection === null) {
            $port = $config['db_port'] ?? 3306;

            self::$connection = new \mysqli(
                $config['db_host'],
                $config['db_user'],
                $config['db_pass'],
                $config['db_name'],
                $port
            );

            self::$connection->set_charset('utf8mb4');
        }

        return self::$connection;
    }
}