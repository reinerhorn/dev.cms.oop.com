<?php
 
declare(strict_types=1);


require_once dirname(__DIR__) . '/vendor/autoload.php';

use CMS\Core\CMSApp;
use CMS\Core\Router;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;
// ZENTRALER ENTRYPOINT: CMS zuerst initialisieren
CMSApp::init();

// Danach Router (AJAX / API / POST)
Router::dispatch();
if (str_starts_with($_SERVER['REQUEST_URI'], '/ajax/')) {
    exit;
}


// 🔹 Debug
$appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';
$isDebug = $appEnv !== 'production';

ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('display_startup_errors', $isDebug ? '1' : '0');
error_reporting($isDebug ? E_ALL : 0);

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
    http_response_code(404);
    exit('Seite nicht gefunden');
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
// 🔹 Übergabe an PageController (einzige Render-Stelle)
$pageController = new \CMS\Core\Controller\PageController(
    $twig,
    CMSApp::getDb(),
    \CMS\Core\CMSAppFrontend::class
);

$pageController->handle($language, $pageId);