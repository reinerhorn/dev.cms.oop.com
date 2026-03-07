<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Core\Util\Uuid;
use CMS\Repository\User\UserRepository;
use CMS\Application\FormAction\FormActionInterface;

final class RegisterAction implements FormActionInterface
{
    public function handle(array $data): array
    {
        $db = \CMS\Core\CMSApp::getDb();

        // hier direkt mit $db arbeiten
    }
}
    
    

    public function handle(array $data): array
    {
         die('REGISTER ACTION WIRD AUFGERUFEN');
        // 1. Pflichtfelder
        if (empty($data['email']) || empty($data['password'])) {
            return [
                'success' => false,
                'error'   => 'Pflichtfelder fehlen',
            ];
        }

        // 2. E-Mail prüfen
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error'   => 'Ungültige E-Mail-Adresse',
            ];
        }

        // 3. Existiert User bereits?
        if ($this->userRepository->emailExists($data['email'])) {
            return [
                'success' => false,
                'error'   => 'E-Mail bereits registriert',
            ];
        }

        // 4. User anlegen  ✅
        $this->userRepository->create([
            'id'           => Uuid::v4(),                     // 🔴 MUSS 'id' heißen
            'email'        => $data['email'],
            'password'     => password_hash(
                $data['password'],
                PASSWORD_DEFAULT
            ),
            'role_id'      => 'member-role-001',
            'verify_token' => bin2hex(random_bytes(32)),
        ]);

        // 5. Erfolg
        return [
            'success'  => true,
            'message'  => 'Registrierung erfolgreich',
            'redirect' => '/login',
        ];
    }
}