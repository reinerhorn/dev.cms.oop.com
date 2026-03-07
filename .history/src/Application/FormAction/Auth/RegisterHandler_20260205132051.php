<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

final class RegisterHandler
{
    public function handle(array $postData, array $pageMeta): array
    {
        $db = \CMS\Core\CMSApp::getDb();

        // Pflichtfelder prüfen
        if (empty($postData['email']) || empty($postData['password'])) {
            return [
                'success' => false,
                'errors' => [
                    'form' => 'E-Mail und Passwort sind erforderlich'
                ]
            ];
        }

        // E-Mail validieren
        if (!filter_var($postData['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'errors' => [
                    'email' => 'Ungültige E-Mail-Adresse'
                ]
            ];
        }

        // Prüfen, ob User bereits existiert
        $stmt = $db->prepare(
            'SELECT id FROM login_users WHERE email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $postData['email']);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            return [
                'success' => false,
                'errors' => [
                    'email' => 'Diese E-Mail ist bereits registriert'
                ]
            ];
        }
        $stmt->close();

        // User-Daten vorbereiten
        $userId = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
        $password    = password_hash($postData['password'], PASSWORD_DEFAULT);
        $verifyToken = bin2hex(random_bytes(32));
        $roleId      = 'member-role-001';

        // User speichern (nicht verifiziert)
        $stmt = $db->prepare(
            'INSERT INTO login_users
             (id, email, password, role_id, is_verified, verify_token)
             VALUES (?, ?, ?, ?, 0, ?)'
        );
        $stmt->bind_param(
            'sssss',
            $userId,
            $postData['email'],
            $password,
            $roleId,
            $verifyToken
        );
        $stmt->execute();
        $stmt->close();

        // Verifizierungs-Mail senden
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $verifyUrl = sprintf(
            '%s://%s/auth/verify?token=%s',
            $scheme,
            $host,
            $verifyToken
        );

        $mailService = new \CMS\Service\MailService();
        $mailService->send(
            $postData['email'],
            'Bitte bestätige deine Registrierung',
            '<p>Bitte bestätige deine Registrierung:</p>
             <p><a href="' . $verifyUrl . '">' . $verifyUrl . '</a></p>'
        );

        return [
            'success' => true,
            'message' => 'Registrierung erfolgreich. Bitte bestätige deine E-Mail.'
        ];
    }
}
