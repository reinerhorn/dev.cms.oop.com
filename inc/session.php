<?php
CMSLoginSession::initSession();
// Statt erneutem require_once:

require_once $_SERVER['DOCUMENT_ROOT'] . "/CMSApp.php"; 
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class CMSLoginSession {
    public static function initSession() {
        session_start();
    }

    public static function handleLogin() {
        if(self::getPageIdRequested() != '1692888607') {
            return;
        }
        if(self::sessionExists()) {
            $role = $_SESSION['role'] ?? 0;
            $name = $role == 1 ? 'ADMIN-START' : 'MEMBER_BEREICH';
            /* $name = $role == 1 ? 'ADMIN-BEREICH' : 'MEMBER_BEREICH'; */
            $page_id = $role == 1 ? '1695451523' : '1741942625';
            self::redirect($page_id);
        } elseif(isset($_REQUEST['email']) && isset($_REQUEST['password'])) {
            $db_connection = CMSApp::getDb();
            try {
                $statement = $db_connection->prepare("SELECT * FROM login_users WHERE email=? LIMIT 1");
                $statement->bind_param('s', $_REQUEST['email']);
                $statement->execute();
                $result2 = $statement->get_result();
                if($rec = $result2->fetch_assoc()) {
                    if(password_verify($_REQUEST['password'], $rec['password'])) {
                        $_SESSION['userid'] = $rec['id'];
                        $_SESSION['user_id'] = $rec['id']; // Für MemberProfile-Zugriff
                        $_SESSION['name'] = $rec['username'];
                        $_SESSION['role'] = $rec['role'];
                        $_SESSION['admin_a'] = ($rec['role'] == 1) ? 1 : 0;
                        $_SESSION['email'] = $rec['email'];

                        // Primäre role_id des Benutzers ermitteln und speichern
                        $stmt_role_id = $db_connection->prepare("SELECT role_id FROM user_roles WHERE user_id = ? LIMIT 1");
                        $stmt_role_id->bind_param("s", $rec['id']);
                        $stmt_role_id->execute();
                        $res_role_id = $stmt_role_id->get_result();
                        if ($roleIdRow = $res_role_id->fetch_assoc()) {
                            $_SESSION['role_id'] = $roleIdRow['role_id'];
                        }
                        $stmt_role_id->close();

                        // Rollen und Berechtigungen aus der DB laden
                        $stmt_roles = $db_connection->prepare("
                            SELECT r.id 
                            FROM user_roles ur
                            JOIN roles r ON ur.role_id = r.id
                            WHERE ur.user_id = ?
                        ");
                        $stmt_roles->bind_param("s", $rec['id']);
                        $stmt_roles->execute();
                        $result_roles = $stmt_roles->get_result();
                        $_SESSION['roles'] = [];
                        $firstRoleId = null;
                        while ($roleRow = $result_roles->fetch_assoc()) {
                            $_SESSION['roles'][] = $roleRow['id'];
                            if ($firstRoleId === null) {
                                $firstRoleId = $roleRow['id'];
                            }
                        }
                        // Falls $_SESSION['role_id'] noch nicht gesetzt, hier setzen
                        if (!isset($_SESSION['role_id']) && $firstRoleId !== null) {
                            $_SESSION['role_id'] = $firstRoleId;
                        }
                        $stmt_roles->close();

                        $stmt_perms = $db_connection->prepare("
                            SELECT rp.permission_id 
                            FROM user_roles ur
                            JOIN role_permissions rp ON ur.role_id = rp.role_id
                            WHERE ur.user_id = ?
                        ");
                        $stmt_perms->bind_param("s", $rec['id']);
                        $stmt_perms->execute();
                        $result_perms = $stmt_perms->get_result();
                        $_SESSION['permissions'] = [];
                        while ($permRow = $result_perms->fetch_assoc()) {
                            $_SESSION['permissions'][] = $permRow['permission_id'];
                        }
                        $stmt_perms->close();

                        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        $timestamp = time();
                        $id = uniqid('', true);
                        $userid = $_SESSION['userid'];
                        $has_agreed = isset($_POST['agree']) && $_POST['agree'] === 'ich stimme zu';
                        $agreed_at = $has_agreed ? date('Y-m-d H:i:s') : null;
                        $login_time = date('Y-m-d H:i:s');

                        // Prüfen, ob es einen alten offenen Login gibt (aber nicht zu diesem Zeitpunkt)
                        $stmt_check_login = $db_connection->prepare("
                            SELECT id, login_at 
                            FROM login_agb_log 
                            WHERE user_id = ? AND logout_at IS NULL 
                            ORDER BY login_at DESC 
                            LIMIT 1
                        ");
                        $stmt_check_login->bind_param("s", $userid);
                        $stmt_check_login->execute();
                        $result_login_check = $stmt_check_login->get_result();
                        $existingLog = $result_login_check->fetch_assoc();
                        $stmt_check_login->close();

                        if ($existingLog) {
                            // Nur wenn die letzte login_at deutlich älter ist – z. B. 1 Minute oder mehr
                            $last_login_time = strtotime($existingLog['login_at']);
                            if ($last_login_time < time() - 60) {
                                $stmt_update_logout = $db_connection->prepare("UPDATE login_agb_log SET logout_at = ? WHERE id = ?");
                                $stmt_update_logout->bind_param("ss", $login_time, $existingLog['id']);
                                $stmt_update_logout->execute();
                                $stmt_update_logout->close();
                            }
                        }

                        $stmt = $db_connection->prepare("
                            INSERT INTO login_agb_log (id, user_id, ip_address, user_agent, agreed_at, login_at)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->bind_param(
                            "ssssss",
                            $id,
                            $userid,
                            $ip,
                            $user_agent,
                            $agreed_at,
                            $login_time
                        );
                        $stmt->execute();

                        self::redirect('1692888607');
                    }
                }
            } catch (Exception $e) {
                echo "Fehler bei der Login-Verarbeitung: " . $e->getMessage();
            }
        }
    }

    private static function redirect($page_id) {
        if(!headers_sent()) {
            header('Status: 302 Moved Temporarily', false, 302);
            header('Location: /?page=' . $page_id);
        } else {
            echo '<script type="text/javascript">';
            echo 'window.location.href="?page='.$page_id.'";';
            echo '</script>';
            echo '<noscript>';
            echo '<meta http-equiv="refresh" content="0;url=?page='.$page_id.'" />';
            echo '</noscript>'; exit;
        }
    }

    private static function sessionExists() {
        return isset($_SESSION['email']);
    }

    private static function getPageIdRequested() {
        return isset($_REQUEST['page']) ? $_REQUEST['page'] : null;
    }

    public static function handleUserAction($postData) {
        $connection = CMSApp::getDb(); 
        $id = $postData['id'] ?? "";
        $email = $postData['email'] ?? "";
        $password = $postData['password'] ?? "";
        $username = $postData['username'] ?? "";
        $role = $postData['role'] ?? "";
        $message = "";

        if (!isset($postData['action'])) {
            return compact('id', 'email', 'password', 'username', 'role', 'message');
        }

        $action = $postData['action'];
        if ($action == "store") {
            $action = empty($id) ? "add" : "update";
        }

        if (!self::isValidEmail($email)) {
            $message = "Ungültige E-Mail-Adresse. Bitte eine echte Adresse verwenden.";
            return compact('id', 'email', 'password', 'username', 'role', 'message');
        }

        try {
            // Prüfen, ob E-Mail bereits existiert
            $stmt_check = $connection->prepare("SELECT COUNT(*) FROM login_users WHERE email = ?");
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $result = $stmt_check->get_result();
            $row = $result->fetch_row();
            $count = $row[0] ?? 0;
            $stmt_check->close();

            if ($count > 0) {
                $message = "❌ Diese E-Mail-Adresse ist bereits registriert.";
                return compact('id', 'email', 'password', 'username', 'role', 'message');
            }
            // Prüfen, ob Benutzername bereits existiert
            $stmt_check_username = $connection->prepare("SELECT COUNT(*) FROM login_users WHERE username = ?");
            $stmt_check_username->bind_param("s", $username);
            $stmt_check_username->execute();
            $result_username = $stmt_check_username->get_result();
            $row_username = $result_username->fetch_row();
            $username_count = $row_username[0] ?? 0;
            $stmt_check_username->close();

            if ($username_count > 0) {
                $message = "❌ Dieser Benutzername ist bereits vergeben.";
                return compact('id', 'email', 'password', 'username', 'role', 'message');
            }
            if ($action == "add") {
                $password = password_hash($password, PASSWORD_DEFAULT);
                $id = uniqid('', true);
                $verifyToken = uniqid('', true);
                $roleInt = (int)$role;
                $stmt = $connection->prepare("INSERT INTO login_users (id, email, password, username, role, verify_token, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
                $stmt->bind_param("ssssis", $id, $email, $password, $username, $roleInt, $verifyToken);
                if ($stmt->execute()) {
                    $message = "Benutzer erfolgreich hinzugefügt. Bitte überprüfen Sie Ihre E-Mails zur Bestätigung.";
                    $emailResult = self::sendConfirmationEmail($email, $username, $verifyToken);
                    if ($emailResult !== true) {
                        $message .= " " . $emailResult;
                    }
                } else {
                    $message = "Fehler beim Hinzufügen des Benutzers.";
                }
            } elseif ($action == "update") {
                $stmt = $connection->prepare("UPDATE login_users SET email=?, username=?, role=? WHERE id=?");
                $stmt->bind_param("sssi", $email, $username, $role, $id);
                if ($stmt->execute()) {
                    $message = "Benutzerdaten erfolgreich aktualisiert.";
                } else {
                    $message = "Fehler beim Aktualisieren der Benutzerdaten.";
                }
            }
        } catch (Exception $e) {
            $message = "Fehler bei der Datenbankoperation: " . $e->getMessage();
        }

        return compact('id', 'email', 'password', 'username', 'role', 'message');
    }

    private static function isValidEmail($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        $local_part = strstr($email, '@', true);
        if (ctype_digit($local_part)) {
            return false;
        }
        
        $domain = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($domain, "MX")) {
            return false;
        }
        
        return true;
    }

    private static function sendConfirmationEmail($email, $username, $token = '') {       
        $resultMessage = "";
        require_once dirname(__DIR__) . '/vendor/autoload.php';
        $smtpConfig = require '/private/conf/smtp_config.php';

        $mail = new PHPMailer(true);         
 
// ... Autoloader laden und $smtpConfig vorbereiten

$mail = new PHPMailer(true);

try {
    // SMTP-Serverdaten setzen
    $mail->isSMTP();
    $mail->Host       = $smtpConfig['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpConfig['username'];
    $mail->Password   = $smtpConfig['password'];
    $mail->SMTPSecure = $smtpConfig['smtp_secure'];
    $mail->Port       = $smtpConfig['port'];
    $mail->CharSet    = $smtpConfig['charset'];
    #$mail->SMTPDebug = 2; // oder 3 für noch mehr Debug-Ausgaben
    #$mail->Debugoutput = 'html'; // macht die Ausgabe lesbar im Browser
    // Absender
    $mail->setFrom('horn.it@t-online.de', 'horn.it');

    // Empfänger dynamisch
    $mail->addAddress($email, $username);

    // Inhalt
    $mail->isHTML(true);
    $mail->Subject = 'Bestätigung deiner Anmeldung';
    $mail->Body    = "Hallo $username,<br>Bitte klicke auf den folgenden Link, um deine Anmeldung zu bestätigen:<br><a href='https://dev.cms-oop.com/protected/verify.php?token=$token'>Konto bestätigen</a>";
    $mail->AltBody = "Hallo $username,\nBitte klicke auf den folgenden Link, um deine Anmeldung zu bestätigen:\nhttps://dev.cms-oop.com/protected/verify.php?token=$token";

    // Mail senden
    $mail->send();
    $resultMessage = "Bestätigungsmail wurde gesendet an $email";
    return $resultMessage;
} catch (Exception $e) {
    $resultMessage = "Fehler beim Versenden der Bestätigungsmail: {$mail->ErrorInfo}";
    return $resultMessage;
}
    
 }
    public static function logout(): void {
        error_log("🔓 Logout-Funktion wurde aufgerufen.");
        file_put_contents(__DIR__ . '/logout.log', "Logout aufgerufen am " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        session_start();

        // Update logout_at in login_agb_log
        $db_connection = CMSApp::getDb();
        $userid = $_SESSION['userid'] ?? null;

        if ($userid) {
            $now = date('Y-m-d H:i:s');
            $stmt_logout = $db_connection->prepare("UPDATE login_agb_log SET logout_at = ? WHERE user_id = ? AND logout_at IS NULL ORDER BY login_at DESC LIMIT 1");
            $stmt_logout->bind_param("ss", $now, $userid);
            $stmt_logout->execute();
            $stmt_logout->close();
        }

        session_unset();
        session_destroy();
        header("Location: /index.php");
        exit;
    }
}

$userData = CMSLoginSession::handleUserAction($_POST);
$loggedInAdmin = $_SESSION['admin'] ?? 0;

CMSLoginSession::handleLogin();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    CMSLoginSession::logout();
}
?>