<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Core\Util\Uuid;
use CMS\Application\FormAction\FormActionInterface;

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

        // 6. Erfolg
        return [
            'success'  => true,
            'message'  => 'Registrierung erfolgreich',
            'redirect' => '/login',
        ];
    }
}