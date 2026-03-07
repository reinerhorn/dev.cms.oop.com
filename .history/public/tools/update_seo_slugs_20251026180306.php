<?php
$mysqli = new mysqli("localhost", "root", "101@TanZen@101", "dbs_cms_oop");
if ($mysqli->connect_errno) {
    die("Fehler bei Verbindung: " . $mysqli->connect_error);
}

echo "<h3>🔄 Starte SEO-Slug-Update (direkter Modus)...</h3>";

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

$updated = 0;
$stmt = $mysqli->prepare("UPDATE navigation SET seo_slug = ? WHERE fk_translation_placeholder = ? AND (seo_slug IS NULL OR seo_slug = '')");

foreach ($mapping as $placeholder => $slug) {
    $stmt->bind_param('ss', $slug, $placeholder);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $updated += $stmt->affected_rows;
        echo "✅ Aktualisiert: {$placeholder} → {$slug}<br>";
    }
}

$stmt->close();
$mysqli->close();

echo "<strong>🎉 SEO-Slug-Update abgeschlossen. Aktualisierte Einträge: {$updated}</strong>";
?>