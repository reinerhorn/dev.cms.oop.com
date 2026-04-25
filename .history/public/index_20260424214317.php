<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// 🔥 Debug Settings
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

error_log('🔥 TEST LOG');
error_log('=== REQUEST START ===');
error_log('METHOD: ' . ($_SERVER['REQUEST_METHOD'] ?? 'NULL'));
error_log('POST: ' . print_r($_POST, true));
error_log('LOG TARGET: ' . ini_get('error_log'));
error_log('BOOTSTRAP OK');

use CMS\Core\CMSApp;
use CMS\Controller\PageController;
use CMS\Routing\Router;
use Twig\Loader\FilesystemLoader;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\DebugExtension;
use CMS\Core\Environment;
require_once dirname(__DIR__) . '/src/Core/Environment.php';

// 🔥 ENV INIT DEBUG
error_log('AAA VOR ENV');
Environment::init();
error_log('BBB NACH ENV');

// 🔹 CMS init
CMSApp::init();

// 🚨 CENTRAL POST DISPATCH
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

    $formResult = [
        'success' => true,
        'redirect' => $_SERVER['HTTP_REFERER'] ?? '/'
    ];

    $_SESSION['form_result'] = $formResult;

    header('Location: ' . $formResult['redirect']);
    exit;
}

error_log('🔥 BEFORE ROUTER');
Router::dispatch();

if (str_starts_with($_SERVER['REQUEST_URI'], '/ajax/')) {
    exit;
}
error_log('🔥 AFTER ROUTER');

// 🔹 Debug Mode
$appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';
$isDebug = $appEnv !== 'production';

ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('display_startup_errors', $isDebug ? '1' : '0');
error_reporting($isDebug ? E_ALL : 0);

// 🔹 URL Analyse
$requestUri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts = array_values(array_filter(explode('/', $requestUri)));

if (empty($parts)) {
    header('Location: /de/startseite');
    exit;
}

$language = $parts[0] ?? null;
$pageId   = $parts[1] ?? null;

if (!preg_match('/^[a-z]{2}$/', $language)) {
    $language = 'de';
}

if ($pageId !== null) {
    $pageId = strtolower($pageId);
}

CMSApp::setLanguage($language);

// 🔹 Twig Setup
$loader = new FilesystemLoader(dirname(__DIR__) . '/src/Templates');
$twig = new TwigEnvironment($loader, [
    'cache' => $isDebug ? false : dirname(__DIR__) . '/cache/twig',
    'debug' => $isDebug,
    'auto_reload' => true,
]);

if ($isDebug) {
    $twig->addExtension(new DebugExtension());
}

// 🔹 Render
$pageController = new PageController(
    $twig,
    CMSApp::getDb()
);

$pageController->handle($language, $pageId);