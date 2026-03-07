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

        $session = new CMSLoginSession();

        if (!$session->login($data['email'], $data['password'])) {
            return [
                'success' => false,
                'message' => 'Login fehlgeschlagen'
            ];
        }

        // ✅ Redirect gehört HIERHER, nicht in die Session
        return [
            'success'  => true,
            'redirect' => '/de/adminbereich'
        ];
    }
}