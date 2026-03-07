<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use CMS\Core\EnvLoader;
use CMS\Service\MailService;

// ENV laden (wichtig!)
EnvLoader::load(__DIR__ . '/../.env');

echo "CMS_MAIL_CONFIG = " . getenv('CMS_MAIL_CONFIG') . PHP_EOL;

// Mail testen
$mail = new MailService();

$mail->send(
    'deine-mail@domain.de',
    'PHPMailer Test',
    '<h1>Test erfolgreich</h1><p>Mail kommt aus dem Terminal 🚀</p>'
);

echo "Mail wurde versendet ✔️" . PHP_EOL;
