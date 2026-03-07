<?php
$mysqli = new mysqli("localhost", "root", "101@TanZen@101", "dbs_cms_oop");
if ($mysqli->connect_errno) {
    die("Fehler bei Verbindung: " . $mysqli->connect_error);
}

echo "<h3>🔄 Starte SEO-Slug-Update (sicherer Modus ohne Trigger)...</h3>";

// Mapping zwischen Platzhaltern und Slugs
$mapping = [
    'PAGE_START_LABEL' => 'startseite',
    'PAGE_COMPANY_LABEL' => 'unternehmen',
    'PAGE_PRESS_LABEL' => 'presse',
    'PAGE_CONTACT_LABEL' => 'kontakt',
    'PAGE_LOGIN_AREA_LABEL' => 'anmeldung',
    'PAGE_LOGIN_LABEL' => 'login',
    'PAGE_SIGNUP_LABEL' => 'registrieren',
    'PAGE_IMPRINT_LABEL' => 'impressum',
    'PAGE_LOGOUT_LABEL' => 'logout',
    'MEMBER_PAGE_START_LABEL' => 'member-start',
    'ADMIN_PAGE_START_LABEL' => 'admin-start',
    'ADMIN_PAGE_EDITOR_LABEL' => 'seiten-editor',
    'ADMIN_FORMULAR_EDITOR' => 'formular-editor',
    'ADMIN_CARD_EDITOR' => 'karten-editor',
    'ADMIN_START_WEB_BESUCHER' => 'web-besucher',
    'ADMIN_ROLE' => 'rollen',
    'ADMIN_USER_ROLES' => 'benutzerrollen',
    'ADMIN_LANGUAGE_EDITOR' => 'sprach-editor',
    'ADMIN_SYSTEM_CHECKER' => 'system-checker',
    'ADMIN_STATISTIK' => 'statistik',
    'ADMIN_STATISTIKEN' => 'statistiken'
];

// 🧩 Schritt 1: Alle relevanten Navigationseinträge in PHP laden
$query = "SELECT nav_uuid, fk_translation_placeholder FROM navigation";
$result = $mysqli->query($query);

if (!$result) {
    die("Fehler beim Abrufen der Navigationseinträge: " . $mysqli->error);
}

$allRows = $result->fetch_all(MYSQLI_ASSOC);
$result->free();

$updated = 0;
$stmtUpdate = $mysqli->prepare("UPDATE navigation SET seo_slug = ? WHERE nav_uuid = ?");

foreach ($allRows as $row) {
    $placeholder = $row['fk_translation_placeholder'] ?? null;
    $uuid = $row['nav_uuid'] ?? null;

    if (!$placeholder || !$uuid) {
        continue;
    }

    if (isset($mapping[$placeholder])) {
        $slug = $mapping[$placeholder];
        $stmtUpdate->bind_param('ss', $slug, $uuid);
        $stmtUpdate->execute();
        if ($stmtUpdate->affected_rows > 0) {
            echo "✅ {$placeholder} → {$slug}<br>";
            $updated++;
        }
    }
}

$stmtUpdate->close();
$mysqli->close();

echo "<strong>🎉 SEO-Slug-Update abgeschlossen. Aktualisierte Einträge: {$updated}</strong>";
?>