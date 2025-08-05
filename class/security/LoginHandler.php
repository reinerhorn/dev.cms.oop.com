<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/CMSApp.php"; 
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";

// Admin-Zugriffsprüfung (außer im CLI-Kontext)
if (php_sapi_name() !== 'cli') {
    $loginHandler = LoginHandler::getInstance();
    $user = $loginHandler->getUser();

    $currentPage = $_GET['page'] ?? '';
    if (!isset($_SESSION['user_id'])) {
        // Loginseite IMMER zugänglich lassen
        if ($currentPage !== '1692888607') {
            $_SESSION['login_result'] = ['message' => '⚠️ Bitte zuerst einloggen.'];
            header("Location: /?page=1692888607");
            exit;
        }
    } else {
        // Zugriff nur beschränken, wenn aktuelle Seite eine Admin-Seite ist
        // Liste der geschützten Admin-Seiten-IDs
        $adminPages = ['1695451523'];
        $loginPage = '1692888607';

        if (!isset($_SESSION['user_id'])) {
            if ($currentPage !== $loginPage) {
                $_SESSION['login_result'] = ['message' => '⚠️ Bitte zuerst einloggen.'];
                header("Location: /?page=$loginPage");
                exit;
            }
        } else {
            if (in_array($currentPage, $adminPages)) {
                if (!in_array($user->role_id ?? '', ['admin-role-001', 'admin-role-002'])) {
                    $_SESSION['login_result'] = ['message' => '⛔ Kein Zugriff – Adminrechte erforderlich.'];
                    header("Location: /?page=$loginPage");
                    exit;
                }
            }
        }
    }
}
class LoginHandler {
    private static $instance = null;
    private $user = null;

   private function __construct() {
    $this->initSession();
}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new LoginHandler();
        }
        return self::$instance;
    }

    public function initSession() {
        if (isset($_SESSION['user_id'])) {
            $this->user = $this->loadUserById($_SESSION['user_id']);
        }
    }

    public function handleUserAction() {
        unset($_SESSION['login_result']);
        if (isset($_POST['login'])) {
            $usernameInput = $_POST['username'] ?? $_POST['email'] ?? 'leer';
            error_log("🔑 Benutzername/E-Mail: $usernameInput, Passwort: " . ($_POST['password'] ?? 'leer'));
            $username = $_POST['username'] ?? $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $this->handleLogin($username, $password);
        } elseif (isset($_GET['logout'])) {
            $this->logout();
        }
    }

    public function handleLogin($username, $password) {
        $user = $this->authenticate($username, $password);
        if ($user) {
            error_log("✅ Login erfolgreich für Benutzer: " . $user->username);
            $_SESSION['user_id'] = $user->id;
            $_SESSION['email'] = $user->email ?? '';
            $_SESSION['name'] = $user->username ?? '';
            $_SESSION['role_id'] = $user->role_id ?? null;
            $_SESSION['admin'] = in_array($user->role_id ?? '', ['admin-role-001', 'admin-role-002']) ? 1 : 0;
            $_SESSION['admin_a'] = $_SESSION['admin'];
            $this->user = $user;
            $_SESSION['login_result'] = ['message' => '✅ Login erfolgreich.'];
            $isAdmin = ($user->role_id === 'admin-role-001' || $user->role_id === 'admin-role-002');
            $targetPage = $isAdmin ? '1695451523' : '1741942625';
            error_log("🔁 Weiterleitung zu: /?page={$targetPage}");
            if (!headers_sent()) {
                header("Location: /?page={$targetPage}");
                exit;
            } else {
                echo "<script>window.location.href='/?page={$targetPage}';</script>";
                echo "<noscript><meta http-equiv='refresh' content='0; url=/?page={$targetPage}'></noscript>";
                exit;
            }
        } else {
            error_log("❌ Login fehlgeschlagen.");
            // handle login failure
            $_SESSION['login_result'] = ['message' => '❌ Benutzername oder Passwort ist falsch.'];
        }
    }

    public function logout() {
        session_destroy();
        $this->user = null;
    }

    private function loadUserById($userId) {
        $db = CMSApp::getDb();
        $stmt = $db->prepare("SELECT * FROM login_users WHERE id = ?");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_object() ?: null;
    }

    private function authenticate($username, $password) {
        $db = CMSApp::getDb();
        $stmt = $db->prepare("SELECT * FROM login_users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result) {
            error_log("❗ Authentifizierungs-Abfrage fehlgeschlagen: " . $stmt->error);
        }

        if ($user = $result->fetch_object()) {
            if (password_verify($password, $user->password)) {
                return $user;
            }
        }
        return null;
    }

    public function getUser() {
        return $this->user;
    }
}

$login = LoginHandler::getInstance();
$login->handleUserAction();

 

