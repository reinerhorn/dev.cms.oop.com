<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use CMS\Core\CMSApp;
use CMS\Core\Router;

// 🔑 GANZ WICHTIG: CMS initialisieren (Session, Rolle, Sprache)
CMSApp::init();

// Danach erst Router
Router::dispatch();
