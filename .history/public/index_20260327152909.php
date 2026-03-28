<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/vendor/autoload.php";

use CMS\Core\CMSApp;
use CMS\Core\CMSAppFrontend;

// CMSApp initialisieren
CMSApp::init();

// URL-Pfad extrahieren und Segmente bestimmen
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments = array_values(array_filter(explode('/', $path)));

// Sprache und Slug aus URL
$lang = $segments[0] ?? 'de';
$slug = $segments[1] ?? 'startseite';

// Debug Logs
error_log('SLUG RECEIVED: ' . $slug);
error_log('LANG RECEIVED: ' . $lang);

// Sprache setzen
CMSApp::setLanguage($lang);

// Twig initialisieren
$loader = new \Twig\Loader\FilesystemLoader(dirname(__DIR__) . '/src/Templates');
$twig = new \Twig\Environment($loader, [
    'cache' => false, // Dev Mode
    'debug' => true,
]);

// Seite rendern
try {
    echo CMSAppFrontend::renderBySlug($twig, $slug, $lang);
} catch (\Exception $e) {
    http_response_code(500);
    echo "<h1>Fehler beim Laden der Seite</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    error_log('Render Error: ' . $e->getMessage());
}
