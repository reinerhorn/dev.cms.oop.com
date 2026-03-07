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

        $db = \CMS\Core\CMSApp::getDb();

        $stmt = $db->prepare(
            'SELECT id, password, role_id, is_verified
             FROM login_users
             WHERE email = ?
             LIMIT 1'
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
                'message' => 'Interner Fehler'
            ];
        }

        $stmt->bind_result($id, $passwordHash, $roleId, $isVerified);

        if (!$stmt->fetch()) {
            error_log('LOGIN DEBUG: USER NOT FOUND');
            $stmt->close();
            return [
                'success' => false,
                'message' => 'Login fehlgeschlagen'
            ];
        }

        $stmt->close();

        if ((int)$isVerified !== 1) {
            error_log('LOGIN DEBUG: USER NOT VERIFIED');
            return [
                'success' => false,
                'message' => 'Account ist nicht verifiziert'
            ];
        }

        if (!is_string($passwordHash) || !password_verify($data['password'], $passwordHash)) {
            error_log('LOGIN DEBUG: PASSWORD VERIFY FAILED');
            return [
                'success' => false,
                'message' => 'Login fehlgeschlagen'
            ];
        }

        error_log('LOGIN DEBUG: LOGIN OK user=' . $id . ' role=' . $roleId);

        return CMSLoginSession::login($id, $roleId);
    }
}