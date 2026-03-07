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

        // Redirect datengetrieben über Rolle → Startseite (Page)
        $stmt = $db->prepare(
            'SELECT p.slug
             FROM roles r
             JOIN pages p ON p.id = r.start_page_id
             WHERE r.id = ?
             LIMIT 1'
        );
        $stmt->bind_param('s', $user['role_id']);
        $stmt->execute();
        $page = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $redirect = $page && !empty($page['slug'])
            ? '/de/' . $page['slug']
            : '/de/startseite';

        CMSLoginSession::setLoginRedirect($redirect);

        return [
            'success'  => true,
            'redirect' => $redirect
        ];
    }
}