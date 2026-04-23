<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true); // true für Exception-Handling

try {
    echo "PHPMailer wurde erfolgreich geladen!";
} catch (Exception $e) {
    echo "Fehler: " . $mail->ErrorInfo;
}