<?php
 
declare(strict_types=1);
 
namespace CMS\Core;
 
final class Environment
{
    public static function init(): void
    {
       // DEBUG vor Umschaltung des Logs (geht noch ins Apache/PHP Default-Log)
       error_log('>>> ENV INIT START (BEFORE ini_set)');
        $logFile = dirname(__DIR__, 2) . '/logs/php-error.log';

        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }

        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);

        // DEBUG nach Umschaltung (muss jetzt in dein Projekt-Log gehen)
        error_log('>>> ENV LOG TARGET: ' . ini_get('error_log'));
        error_log('>>> ENV INIT SWITCHED TO PROJECT LOG');

        $env = $_ENV['APP_ENV'] ?? 'development';

        if ($env === 'production') {
            error_reporting(0);
            ini_set('display_errors', '0');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        }
        error_log('>>> ENV INIT END (SHOULD BE IN PROJECT LOG)');
    }
}
