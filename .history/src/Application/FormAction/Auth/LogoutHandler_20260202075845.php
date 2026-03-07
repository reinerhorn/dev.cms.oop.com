<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use CMS\Application\Auth\AuthService;
use CMS\Core\CMSLoginSession;

header('Content-Type: application/json');

$data = $_POST ?: json_decode(file_get_contents('php://input'), true);

$email    = $data['email']    ?? '';
$password = $data['password'] ?? '';

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Fehlende Daten']);
    exit;
}

$auth = new AuthService();
$result = $auth->login($email, $password);

if ($result['ok'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => $result['error']
    ]);
    exit;
}

// 🔐 EINZIGE Stelle mit Session
$response = CMSLoginSession::login(
    $result['userId'],
    $result['roleId']
);

echo json_encode($response);