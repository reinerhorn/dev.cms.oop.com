<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Core\Util\Uuid;
use CMS\Application\FormAction\FormActionInterface;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

final class RegisterAction implements FormActionInterface
{
    public function handle(array $data): array
    {
        // DEBUG – sicherstellen, dass die Action erreicht wird
        // die('REGISTER ACTION WIRD AUFGERUFEN');

        // 1. Pflichtfelder prüfen
        if (empty($data['email']) || empty($data['password'])) {
            return [
                'success' => false,
                'error'   => 'Pflichtfelder fehlen',
            ];
        }

        // 2. E-Mail validieren
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error'   => 'Ungültige E-Mail-Adresse',
            ];
        }

        // 3. DB holen (wie bei LoginAction)
        $db = \CMS\Core\CMSApp::getDb();

        // 4. Prüfen ob E-Mail bereits existiert
        $stmt = $db->prepare(
            'SELECT 1 FROM login_users WHERE email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $data['email']);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->close();
            return [
                'success' => false,
                'error'   => 'E-Mail bereits registriert',
            ];
        }
        $stmt->close();

        // 5. User anlegen
        $userId = Uuid::v4();
        $verifyToken = bin2hex(random_bytes(32));
        $passwordHash = password_hash(
            $data['password'],
            PASSWORD_DEFAULT
        );

        $stmt = $db->prepare(
            'INSERT INTO login_users
             (id, email, password, role_id, is_verified, verify_token)
             VALUES (?, ?, ?, ?, 0, ?)'
        );

        $roleId = 'member-role-001';

        $stmt->bind_param(
            'sssss',
            $userId,
            $data['email'],
            $passwordHash,
            $roleId,
            $verifyToken
        );

        if (!$stmt->execute()) {
            $stmt->close();
            return [
                'success' => false,
                'error'   => 'Registrierung fehlgeschlagen',
            ];
        }

        $stmt->close();

        // 6. Verifizierungs-Mail senden (PHPMailer)
        try {
            $mail = new PHPMailer(true);

            // SMTP-Konfiguration (anpassen falls nötig)
            $mail->isSMTP();
            $mail->Host       = $_ENV['MAIL_HOST'] ?? 'localhost';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USER'] ?? '';
            $mail->Password   = $_ENV['MAIL_PASS'] ?? '';
            $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 587);

            $mail->CharSet = 'UTF-8';

            $mail->setFrom('noreply@dev.cms-oop.com', 'CMS');
            $mail->addAddress($data['email']);

            $verifyLink = 'https://dev.cms-oop.com/verify?token=' . $verifyToken;

            $mail->Subject = 'Bitte bestätige deine Registrierung';
            $mail->Body = <<<MAIL
Hallo,

bitte bestätige deine Registrierung, indem du auf folgenden Link klickst:

$verifyLink

Falls du dich nicht selbst registriert hast, kannst du diese E-Mail ignorieren.

Viele Grüße
Dein CMS
MAIL;

            $mail->send();
        } catch (Exception $e) {
            error_log('MAIL ERROR: ' . $e->getMessage());
        }

        // 7. Erfolg
        return [
            'success'  => true,
            'message'  => 'Registrierung erfolgreich',
            'redirect' => '/login',
        ];
    }
}