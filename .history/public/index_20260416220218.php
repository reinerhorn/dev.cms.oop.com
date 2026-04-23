<?php
declare(strict_types=1);

 error_log("🔥 PROJECT LOG TEST");

/*file_put_contents(
    dirname(__DIR__) . '/logs/hardtest.log',
    'INDEX WURDE AUFGERUFEN' . PHP_EOL,
    FILE_APPEND
);*/
// 🔥 1. Environment IMMER zuerst laden
require_once dirname(__DIR__) . '/src/Core/Environment.php';
Environment::init();

// 🔥 2. Danach Composer Autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// 🔥 3. Debug Settings
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
echo ini_get('error_log');
error_log('🔥 TEST LOG');

// 🔥 TEST (muss jetzt im eigenen Log landen)
error_log('BOOTSTRAP OK');



use CMS\Core\CMSApp;
use CMS\Controller\PageController;
use CMS\Routing\Router;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;

// ZENTRALER ENTRYPOINT: CMS zuerst initialisieren
CMSApp::init();
error_log('🔥 BEFORE ROUTER');
// Danach Router (AJAX / API / POST)
Router::dispatch();
if (str_starts_with($_SERVER['REQUEST_URI'], '/ajax/')) {
    exit;
}
error_log('🔥 AFTER ROUTER');

// 🔹 Debug
$appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';
$isDebug = $appEnv !== 'production';

ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('display_startup_errors', $isDebug ? '1' : '0');
error_reporting($isDebug ? E_ALL : 0);

// 🔹 URL analysieren
$requestUri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts = array_values(array_filter(explode('/', $requestUri)));

// Root-Aufruf "/" → auf Standard-Startseite weiterleiten
if (empty($parts)) {
    header('Location: /de/startseite');
    exit;
}

$language = $parts[0] ?? null;
$pageId   = $parts[1] ?? null;

// ❗ Nur echte Sprachcodes zulassen (z. B. de, en)
// Bei ungültiger Sprache KEIN Redirect mehr,
// sondern sauberer Fallback auf Standard-Sprache
if (!preg_match('/^[a-z]{2}$/', $language)) {
    $language = 'de';
}

if ($pageId !== null) {
    $pageId = strtolower($pageId);
}

// Keine Fallback- oder Redirect-Logik mehr hier.
// Routing- und 404-Entscheidungen übernimmt der Controller.

CMSApp::setLanguage($language);

// 🔹 Twig
$loader = new FilesystemLoader(dirname(__DIR__) . '/src/Templates');
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
$pageController = new PageController(
    $twig,
    CMSApp::getDb()
);

$pageController->handle($language, $pageId);