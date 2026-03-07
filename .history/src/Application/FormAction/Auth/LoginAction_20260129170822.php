<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Application\FormAction\FormActionInterface;
use CMS\Core\CMSLoginSession;

final class LoginAction implements FormActionInterface
{
    public function handle(array $data): array
    {
        error_log('LOGIN DEBUG: handle() reached');
        error_log('LOGIN DEBUG: POST = ' . json_encode($data));

        if (empty($data['email']) || empty($data['password'])) {
            return [
                'success' => false,
                'message' => 'E-Mail oder Passwort fehlt'
            ];
        }

        // DB holen
        $db = \CMS\Core\CMSApp::getDb();

        // Benutzer anhand der E-Mail laden
        $stmt = $db->prepare(
            'SELECT id, password, role_id FROM login_users WHERE email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $data['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$user || !password_verify($data['password'], $user['password'])) {
            return [
                'success' => false,
                'message' => 'Login fehlgeschlagen'
            ];
        }

        // Session korrekt setzen
        CMSLoginSession::login(
            $user['id'],
            $user['role_id']
        );

        // Redirect anhand der Rolle festlegen
        if (in_array($user['role_id'], ['admin-role-001', 'admin-role-002'], true)) {
            CMSLoginSession::setLoginRedirect('/de/adminbereich');
        } elseif ($user['role_id'] === 'member-role-001') {
            CMSLoginSession::setLoginRedirect('/de/memberbereich');
        } else {
            CMSLoginSession::setLoginRedirect('/de/startseite');
        }

        return [
            'success'  => true,
            'redirect' => CMSLoginSession::consumeLoginRedirect()
        ];
    }
}