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

        // Benutzer anhand der E-Mail laden (ohne get_result, mysqlnd-sicher)
        
        $stmt = $db->prepare(
          
            'SELECT id, password, role_id, is_verified FROM login_users WHERE email = ? LIMIT 1'
        );

        if (!$stmt) {
            error_log('LOGIN DEBUG: PREPARE FAILED: ' . $db->error);
            return [
                'success' => false,
                'message' => 'Interner Fehler'
            ];
        }

        $stmt->bind_param('s', $data['email']);

        if ($stmt->execute() !== true) {
            error_log('LOGIN DEBUG: EXECUTE FAILED: ' . $stmt->error);
            $stmt->close();
            return [
                'success' => false,
                'message' => 'Interner Fehler (execute)'
            ];
        }

        error_log('LOGIN DEBUG: EXECUTE OK');

        $stmt->bind_result($id, $passwordHash, $roleId, $isVerified);

        error_log('LOGIN DEBUG: BEFORE FETCH');
        if (!$stmt->fetch()) {
            error_log('LOGIN DEBUG: FETCH RETURNED FALSE');
            error_log('LOGIN DEBUG: USER NOT FOUND');
            $stmt->close();
            return [
                'success' => false,
                'message' => 'Login fehlgeschlagen'
            ];
        }

        if ((int)$isVerified !== 1) {
            error_log('LOGIN DEBUG: USER NOT VERIFIED');
            return [
                'success' => false,
                'message' => 'Account ist nicht verifiziert'
            ];
        }

        /** @var string $id */
        /** @var string $passwordHash */
        /** @var string $roleId */

        $stmt->close();

        error_log('LOGIN DEBUG: USER FOUND id=' . $id . ' role=' . $roleId);
        error_log('LOGIN DEBUG: HASH FROM DB = ' . $passwordHash);
        error_log('LOGIN DEBUG: PASSWORD INPUT = ' . $data['password']);

        if (!password_verify($data['password'], $passwordHash)) {
            return [
                'success' => false,
                'message' => 'Login fehlgeschlagen'
            ];
        }

        return CMSLoginSession::login($id, $roleId);
    }
}