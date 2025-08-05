<?php
// auth_helpers.php

function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['user_id'])) {
        header("Location: /?page=1692888607"); // Loginseite
        exit;
    }
}

function requireAdmin(): void {
    requireLogin(); // Erst prüfen, ob Nutzer eingeloggt ist

    $role = $_SESSION['role_id'] ?? '';
    if (!in_array($role, ['admin-role-001', 'admin-role-002'])) {
        header("Location: /?page=1692888607"); // Loginseite
        exit;
    }
}

