<?php
declare(strict_types=1);
session_start();
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use CMS\Core\Router;

// ZENTRALER ENTRYPOINT FÜR AUTH-AKTIONEN (login, register, logout)
// Erwartet POST + action
Router::dispatch();
