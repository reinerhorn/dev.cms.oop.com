<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['language'])) {
    echo json_encode(['success' => false, 'error' => 'Missing language']);
    exit;
}

$lang = substr($input['language'], 0, 2);
$_SESSION['language'] = $lang;

echo json_encode(['success' => true, 'language' => $lang]);