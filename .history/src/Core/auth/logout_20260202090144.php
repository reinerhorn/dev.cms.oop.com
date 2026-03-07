<?php
declare(strict_types=1);

namespace CMS\Application\Auth;

use CMS\Core\CMSLoginSession;

final class LoginHandler
{
    public function handle(array $data): array
    {
        if (empty($data['email']) || empty($data['password'])) {
            return [
                'success' => false,
                'message' => 'E-Mail oder Passwort fehlt'
            ];
        }

        $service = new AuthService();
        $result  = $service->login(
            $data['email'],
            $data['password']
        );

        if ($result['success'] !== true) {
            return $result;
        }

        return CMSLoginSession::login(
            $result['user_id'],
            $result['role_id']
        );
    }
}
