<php
final class Environment
{
    public static function init(): void
    {
        $logFile = dirname(__DIR__, 2) . '/logs/php-error.log';

        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }

        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);

        $env = $_ENV['APP_ENV'] ?? 'development';

        if ($env === 'production') {
            error_reporting(0);
            ini_set('display_errors', '0');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        }
    }
}
