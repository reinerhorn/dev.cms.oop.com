<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CMS\Controller\Auth\VerifyController;
use CMS\Core\Database\DatabaseConnection;
use CMS\Core\Config\Config;

// Initialisierung
$config = new Config(__DIR__ . '/../config/config.php');
$smtpConfig = require '/private/conf/smtp_config.php';

// DB-Verbindung (über Klasse statt Funktion)
$db = DatabaseConnection::getInstance($config->get('db'));

// Token aus GET holen und validieren
$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);

if (!$token) {
    http_response_code(400);
    echo "Ungültiger oder fehlender Token.";
    exit;
}

// Controller ausführen
$controller = new VerifyController($db, $smtpConfig);
$controller->verify($token);