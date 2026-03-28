<?php
require_once dirname(__DIR__) . "/vendor/autoload.php";
 

use CMS\Core\CMSApp;
use CMS\Core\CMSAppFrontend;
CMSApp::init();
// URL-Pfad extrahieren und in Segmente aufteilen
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments = explode('/', $path);

// Sprache und Slug aus URL extrahieren
$lang = $segments[0] ?? 'de';
$slug = $segments[1] ?? 'startseite';

// Sprache setzen
CMSApp::setLanguage($lang);

// Twig-Environment manuell initialisieren
$loader = new \Twig\Loader\FilesystemLoader(
    dirname(__DIR__) . '/Templates'
);
$twig = new \Twig\Environment($loader);

// Seite rendern mit slug
echo CMSAppFrontend::renderBySlug($twig, $slug, $lang);
?>
