<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Application\FormAction\FormActionInterface;
use CMS\Core\CMSLoginSession;
use CMS\Repository\UserRepository;

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

        // 🔑 User sauber aus der DB laden
        $repo = new UserRepository();
        $user = $repo->findByEmail($data['email']);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Login fehlgeschlagen'
            ];
        }

        if (!password_verify($data['password'], $user['password'])) {
            return [
                'success' => false,
                'message' => 'Login fehlgeschlagen'
            ];
        }

        // ✅ Session korrekt setzen (KEIN Passwort, KEINE Mail!)
        CMSLoginSession::login(
            $user['id'],       // z.B. user-admin-001
            $user['role_id']   // z.B. admin-role-001
        );

        return [
            'success'  => true,
            'redirect' => CMSLoginSession::getRedirectAfterLogin()
        ];
    }
}