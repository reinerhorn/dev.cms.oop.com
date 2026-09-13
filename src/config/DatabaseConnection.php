<?php
declare(strict_types=1);

namespace CMS\Config;

class DatabaseConnection {
    private static ?\mysqli $connection = null;

    public static function getConnection(): \mysqli {
        $start = microtime(true);
        error_log('>>> DB [0.000] START');
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

            error_log('>>> DB [' . number_format(microtime(true) - $start, 6) . '] BEFORE MYSQLI');
            self::$connection = new \mysqli(
                $config['db_host'],
                $config['db_user'],
                $config['db_pass'],
                $config['db_name'],
                $port
            );

            error_log('>>> DB [' . number_format(microtime(true) - $start, 6) . '] AFTER MYSQLI');
            self::$connection->set_charset('utf8mb4');
            error_log('>>> DB [' . number_format(microtime(true) - $start, 6) . '] AFTER CHARSET');
        }

        error_log('>>> DB [' . number_format(microtime(true) - $start, 6) . '] END');
        return self::$connection;
    }
}