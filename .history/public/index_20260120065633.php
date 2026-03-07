<?php
 
declare(strict_types=1);


require_once dirname(__DIR__) . '/vendor/autoload.php';

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// 🔒 Technische Endpunkte vom CMS ausschließen
if (
    str_starts_with($path, 'verify')
    || str_starts_with($path, 'ajax/')
    || str_starts_with($path, 'api/')
) {
    http_response_code(404);
    echo 'Not a CMS page';
    exit;
}

use CMS\Core\CMSApp;
use CMS\Core\CMSAppFrontend;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;

// 🔹 Debug
$appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';
$isDebug = $appEnv !== 'production';

ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('display_startup_errors', $isDebug ? '1' : '0');
error_reporting($isDebug ? E_ALL : 0);

// 🔹 CMS init
CMSApp::init();

// 🔹 URL analysieren
$requestUri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts = array_values(array_filter(explode('/', $requestUri)));

$language = $parts[0] ?? 'de';
$pageId   = $parts[1] ?? null;

// ❗ Nur echte Sprachcodes zulassen (z. B. de, en)
if (!preg_match('/^[a-z]{2}$/', $language)) {
    header('Location: /de/startseite');
    exit;
}

if ($pageId !== null) {
    $pageId = strtolower($pageId);
}

// 🔒 Fallback: keine Seite angegeben → Startseite
if ($pageId === null) {
    header('Location: /' . $language . '/memberberich');
    exit;
}

CMSApp::setLanguage($language);

// 🔹 Twig
$loader = new FilesystemLoader(dirname(__DIR__) . '/templates');
$twig = new Environment($loader, [
    'cache' => $isDebug ? false : dirname(__DIR__) . '/cache/twig',
    'debug' => $isDebug,
    'auto_reload' => true,
]);

if ($isDebug) {
    $twig->addExtension(new DebugExtension());
}

// 🔹 Frontend rendern
try {
    echo CMSAppFrontend::render($twig, $pageId);
} catch (\RuntimeException $e) {

    $status = http_response_code();

    if ($status === 401) {
        header('Location: /' . $language . '/login');
        exit;
    }

    if ($status === 403) {
        echo 'Zugriff verweigert';
        exit;
    }

    if ($isDebug) {
        http_response_code(500);
        echo "<pre style='color:red;'>Frontend Error:\n" . $e . "</pre>";
        exit;
    }

    http_response_code(500);
    echo "Interner Serverfehler";
    exit;
}