<?php
// Pfad zur Konfigurationsdatei laden und $config bereitstellen
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
$smtp_config = require "/private/conf/smtp_config.php";
 
// Verbindung herstellen
$mysqli = getDbConnection();

if ($mysqli->connect_errno) {
    die("Datenbankverbindung fehlgeschlagen: " . $mysqli->connect_error);
}

if (!isset($_GET['token']) || empty($_GET['token'])) {
    die("Ungültiger oder fehlender Token.");
}

$token = $_GET['token'];

// Prepared Statement für SELECT
$stmt = $mysqli->prepare("SELECT id, is_verified FROM login_users WHERE verify_token = ? LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    $stmt->close();
    // Prüfen ob dieser Token bereits benutzt wurde (bereits verifiziert)
    $stmtCheck = $mysqli->prepare("SELECT id FROM login_users WHERE is_verified = 1 AND verify_token IS NULL LIMIT 1");
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    if ($resCheck && $resCheck->num_rows > 0) {
        $stmtCheck->close();
        $mysqli->close();
        header("Location: /?page=1692888607&language=de&already_verified=1");
        exit;
    }
    $stmtCheck->close();
    $mysqli->close();
    echo "Ungültiger Token oder Nutzer nicht gefunden.";
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

if ($user['is_verified']) {
    $mysqli->close();
    header("Location: /?page=1692888607&language=de&already_verified=1");
    exit;
}

// Prepared Statement für UPDATE
$stmtUpdate = $mysqli->prepare("UPDATE login_users SET is_verified = 1, verify_token = NULL WHERE id = ?");
$stmtUpdate->bind_param('s', $user['id']);
if ($stmtUpdate->execute()) {
    // Nach erfolgreichem Update: Bestätigungs-E-Mail senden
    // Hole E-Mail-Adresse des Nutzers
    $userId = $user['id'];
    // E-Mail-Adresse nachladen (falls nicht im $user, sonst: $user['email'])
    $email = null;
    if (isset($user['email'])) {
        $email = $user['email'];
    } else {
        $stmtMail = $mysqli->prepare("SELECT email FROM login_users WHERE id = ? LIMIT 1");
        $stmtMail->bind_param('s', $userId);
        $stmtMail->execute();
        $resultMail = $stmtMail->get_result();
        if ($resultMail && $resultMail->num_rows > 0) {
            $rowMail = $resultMail->fetch_assoc();
            $email = $rowMail['email'];
        }
        $stmtMail->close();
    }

    // PHPMailer laden
    require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mailError = false;
    try {
        $mail->isSMTP();
        $mail->Host = $smtp_config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_config['username'];
        $mail->Password = $smtp_config['password'];
        $mail->SMTPSecure = $smtp_config['smtp_secure'];
        $mail->Port = $smtp_config['port'];
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($smtp_config['username'], 'Dein Projektname');
        $mail->addAddress($email);
        $mail->Subject = "Konto erfolgreich bestätigt";
        $mail->Body = "Hallo,\n\nDein Konto wurde erfolgreich bestätigt. Du kannst dich jetzt anmelden.\n\nViele Grüße\nDas Team";
        $mail->send();
    } catch (Exception $e) {
        $mailError = true;
    }
    $stmtUpdate->close();
    $mysqli->close();
    if ($mailError) {
        header("Location: /?page=1692888607&language=de&verified=1&mail_error=1");
        exit;
    } else {
        header("Location: /?page=1692888607&language=de&verified=1");
        exit;
    }
} else {
    $stmtUpdate->close();
    $mysqli->close();
    echo "Fehler beim Bestätigen deines Kontos.";
}