<?php
declare(strict_types=1);

namespace CMS\Controller;

use CMS\Core\CMSApp;
use CMS\Core\CMSLoginSession;

class RegisterController
{
    public static function handle(array $postData): array
    {
        $userData = CMSLoginSession::handleUserAction($postData);

        // Normalisiere die Rückgabe
        $message = '';
        if (isset($userData['error'])) {
            $message = $userData['error'];
        } elseif (isset($userData['success'])) {
            $message = $userData['success'];
        }

        return [
            'userData' => [
                'id' => $userData['id'] ?? '',
                'email' => $userData['email'] ?? '',
                'username' => $userData['username'] ?? '',
                'password' => $userData['password'] ?? '',
                'message' => htmlspecialchars($message),
            ],
            'agree' => isset($postData['agree']),
        ];
    }
}