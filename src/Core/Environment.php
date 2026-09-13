<?php

declare(strict_types=1);

namespace CMS\Core;

final class Environment
{
    public static function init(): void
    {
        $start = microtime(true);

        error_log('>>> ENV [0.000] START');

        $logFile = dirname(__DIR__, 2) . '/logs/php-error.log';
        error_log('>>> ENV [' . number_format(microtime(true) - $start, 6) . '] LOGFILE BUILT: ' . $logFile);

        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            error_log('>>> ENV [' . number_format(microtime(true) - $start, 6) . '] BEFORE MKDIR');
            mkdir($logDir, 0777, true);
            error_log('>>> ENV [' . number_format(microtime(true) - $start, 6) . '] AFTER MKDIR');
        }

        error_log('>>> ENV [' . number_format(microtime(true) - $start, 6) . '] BEFORE ini_set log_errors');
        ini_set('log_errors', '1');

        error_log('>>> ENV [' . number_format(microtime(true) - $start, 6) . '] BEFORE ini_set error_log');
        ini_set('error_log', $logFile);

        error_log('>>> ENV [' . number_format(microtime(true) - $start, 6) . '] LOG SWITCHED');

        $env = $_ENV['APP_ENV'] ?? 'development';
        error_log('>>> ENV [' . number_format(microtime(true) - $start, 6) . '] APP_ENV: ' . $env);

        if ($env === 'production') {
            error_reporting(0);
            ini_set('display_errors', '0');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        }

        error_log('>>> ENV [' . number_format(microtime(true) - $start, 6) . '] END');
    }
}
