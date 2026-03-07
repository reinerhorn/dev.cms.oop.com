<?php
$mysqli = new mysqli("localhost", "root", "101@TanZen@101", "dbs_cms_oop");
if ($mysqli->connect_errno) {
    die("Fehler bei Verbindung: " . $mysqli->connect_error);
}

echo "<h3>🔄 Starte SEO-Slug-Update...</h3>";

// 1️⃣ Temporäre Daten als *persistente* Hilfstabelle speichern
$mysqli->query("DROP TABLE IF EXISTS tmp_navigation");
$createTmp = "
CREATE TABLE tmp_navigation AS
SELECT 
    nav_uuid,
    CASE fk_translation_placeholder
        WHEN 'PAGE_START_LABEL'            THEN 'startseite'
        WHEN 'PAGE_COMPANY_LABEL'          THEN 'unternehmen'
        WHEN 'PAGE_PRESS_LABEL'            THEN 'presse'
        WHEN 'PAGE_CONTACT_LABEL'          THEN 'kontakt'
        WHEN 'PAGE_LOGIN_AREA_LABEL'       THEN 'anmeldung'
        WHEN 'PAGE_LOGIN_LABEL'            THEN 'login'
        WHEN 'PAGE_SIGNUP_LABEL'           THEN 'registrieren'
        WHEN 'PAGE_IMPRINT_LABEL'          THEN 'impressum'
        WHEN 'PAGE_LOGOUT_LABEL'           THEN 'logout'
        WHEN 'MEMBER_PAGE_START_LABEL'     THEN 'member-start'
        WHEN 'ADMIN_PAGE_START_LABEL'      THEN 'admin-start'
        WHEN 'ADMIN_PAGE_EDITOR_LABEL'     THEN 'seiten-editor'
        WHEN 'ADMIN_FORMULAR_EDITOR'       THEN 'formular-editor'
        WHEN 'ADMIN_CARD_EDITOR'           THEN 'karten-editor'
        WHEN 'ADMIN_START_WEB_BESUCHER'    THEN 'web-besucher'
        WHEN 'ADMIN_ROLE'                  THEN 'rollen'
        WHEN 'ADMIN_USER_ROLES'            THEN 'benutzerrollen'
        WHEN 'ADMIN_LANGUAGE_EDITOR'       THEN 'sprach-editor'
        WHEN 'ADMIN_SYSTEM_CHECKER'        THEN 'system-checker'
        WHEN 'ADMIN_STATISTIK'             THEN 'statistik'
        WHEN 'ADMIN_STATISTIKEN'           THEN 'statistiken'
        ELSE NULL
    END AS seo_slug
FROM navigation
WHERE fk_translation_placeholder IS NOT NULL;
";

if (!$mysqli->query($createTmp)) {
    die("❌ Fehler beim Erstellen von tmp_navigation: " . $mysqli->error);
}

echo "✅ Temporäre Tabelle tmp_navigation wurde erstellt.<br>";

// 2️⃣ Neue Verbindung für das Update (verhindert Self-Reference-Fehler)
$mysqli2 = new mysqli("localhost", "root", "101@TanZen@101", "dbs_cms_oop");
if ($mysqli2->connect_errno) {
    die("Fehler bei 2. Verbindung: " . $mysqli2->connect_error);
}

echo "🔧 Aktualisiere SEO-Slugs einzeln...<br>";

$result = $mysqli2->query("SELECT nav_uuid, seo_slug FROM tmp_navigation WHERE seo_slug IS NOT NULL");

$updatedCount = 0;
while ($row = $result->fetch_assoc()) {
    $uuid = $mysqli2->real_escape_string($row['nav_uuid']);
    $slug = $mysqli2->real_escape_string($row['seo_slug']);
    $mysqli2->query("UPDATE navigation SET seo_slug = '$slug' WHERE nav_uuid = '$uuid' AND (seo_slug IS NULL OR seo_slug = '')");
    if ($mysqli2->affected_rows > 0) {
        $updatedCount++;
    }
}

echo "✅ $updatedCount SEO-Slugs erfolgreich aktualisiert!<br>";

// 3️⃣ Aufräumen
$mysqli2->query("DROP TABLE IF EXISTS tmp_navigation");

echo "🧹 Temporäre Tabelle wurde gelöscht.<br>";
echo "<strong>✅ Vorgang abgeschlossen!</strong>";

$mysqli->close();
$mysqli2->close();