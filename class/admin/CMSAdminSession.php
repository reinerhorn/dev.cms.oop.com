<?php

class CMSAdminSession {

    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function checkAccess(): void {
        self::start();
        if (empty($_SESSION['admin_a']) || $_SESSION['admin_a'] != 1) {
            header('Location: /index.php');
            exit;
        }
    }

    public static function getUsername(): string {
        self::start();
        return $_SESSION['name'] ?? 'Unbekannt';
    }

    public static function logout(): void {
        self::start();

        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];

            require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.inc.php';
            $db = getDbConnection();

            $stmt = $db->prepare("UPDATE login_agb_log SET logout_at = NOW() WHERE user_id = ? AND logout_at IS NULL ORDER BY login_at DESC LIMIT 1");
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $stmt->close();
        }

        session_destroy();
        header('Location: /index.php');
        exit;
    }
}