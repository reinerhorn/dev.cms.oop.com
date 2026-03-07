<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use CMS\Application\Auth\AjaxAuthController;

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$controller = new AjaxAuthController();
echo json_encode($controller->login($data));
