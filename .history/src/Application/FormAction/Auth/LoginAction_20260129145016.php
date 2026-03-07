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

     return [
    'success'  => true,
    'redirect' => $session->getRedirectAfterLogin()
];
    }
}