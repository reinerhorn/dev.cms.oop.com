<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/vendor/autoload.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/init.php";

use CMS\Core\CMSApp;
use CMS\Core\CMSAppFrontend;

// Optional: Sprache setzen
if (isset($_GET['lang'])) {
    CMSApp::setLanguage($_GET['lang']);
}

// Seite aus Request (Slug) → später auf page_uuid mappen
$pageId = $_REQUEST['page'] ?? 'startseite';

// Twig-Environment manuell initialisieren
$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/src/Templates');
$twig = new \Twig\Environment($loader);

// Rendern der kompletten Seite (Header, Navigation, Content, Footer)
echo CMSAppFrontend::render($twig, $pageId);
?>
