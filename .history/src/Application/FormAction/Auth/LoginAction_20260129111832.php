<?php
namespace CMS\Application\FormAction\Auth;

use CMS\Application\FormAction\FormActionInterface;

final class LoginAction implements FormActionInterface
{
    public function handle(array $data): array
    {
        // 1. Validierung
        if (empty($data['email']) || empty($data['password'])) {
            return [
                'success' => false,
                'error'   => 'E-Mail oder Passwort fehlt',
            ];
        }

        // 2. Login-Logik (Pseudo)
        $user = /* user laden */ null;

        if (!$user) {
            return [
                'success' => false,
                'error'   => 'Ungültige Zugangsdaten',
            ];
        }

        // 3. Session setzen
        // CMSLoginSession::login($user);

        // 4. Erfolg
        return [
            'success'  => true,
            'redirect' => '/dashboard',
        ];
    }
}