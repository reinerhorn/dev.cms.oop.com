<?php
$mysqli = new mysqli("localhost", "root", "101@TanZen@101", "dbs_cms_oop");
if ($mysqli->connect_errno) {
    die("Fehler bei Verbindung: " . $mysqli->connect_error);
}

$sql = "
UPDATE navigation
SET seo_slug = CASE fk_translation_placeholder
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
    ELSE seo_slug
END
WHERE seo_slug IS NULL OR seo_slug = '';
";

if ($mysqli->query($sql)) {
    echo "✅ SEO-Slugs erfolgreich aktualisiert!";
} else {
    echo "❌ Fehler: " . $mysqli->error;
}

$mysqli->close();