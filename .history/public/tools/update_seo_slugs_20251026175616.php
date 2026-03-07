<?php
$mysqli = new mysqli("localhost", "root", "101@TanZen@101", "dbs_cms_oop");
if ($mysqli->connect_errno) {
    die("Fehler bei Verbindung: " . $mysqli->connect_error);
}

// 1️⃣ Temporäre Tabelle mit Zielwerten anlegen
$mysqli->query("DROP TEMPORARY TABLE IF EXISTS tmp_navigation");
$createTmp = "
CREATE TEMPORARY TABLE tmp_navigation AS
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
FROM navigation;
";
if (!$mysqli->query($createTmp)) {
    die("Fehler beim Erstellen der temporären Tabelle: " . $mysqli->error);
}

// 2️⃣ Navigation mit den neuen Slugs aktualisieren
$update = "
UPDATE navigation n
JOIN tmp_navigation t ON n.nav_uuid = t.nav_uuid
SET n.seo_slug = t.seo_slug
WHERE (n.seo_slug IS NULL OR n.seo_slug = '') AND t.seo_slug IS NOT NULL
";
if ($mysqli->query($update)) {
    echo "✅ SEO-Slugs erfolgreich aktualisiert!";
} else {
    echo "❌ Fehler beim Update: " . $mysqli->error;
}

$mysqli->close();