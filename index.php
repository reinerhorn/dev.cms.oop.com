<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/inc/session.php"; 
include_once $_SERVER['DOCUMENT_ROOT'] . "/inc/web_besucher.php";

// Datenbankverbindung
$main_db_connection = getDbConnection();
if (!$main_db_connection) {
    die("Datenbankverbindung fehlgeschlagen");
}
 
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Agentur / Webdesign / Dienstleistung / Personal / Vermittlung / Lohn</title>
<link title="H & D Dienstleistungen SRL" rel="stylesheet" type="text/css" href="/css/style.css" media="screen">
<link title="H & D Dienstleistungen SRL" rel="stylesheet" type="text/css" href="/css/checkbox.css" media="screen">   
<link title="H & D Dienstleistungen SRL" rel="stylesheet" type="text/css" href="/css/navi.css" media="screen">    
    <link title="H & D Dienstleistungen SRL" rel="stylesheet" type="text/css" href="/css/services.css" media="screen">
    <link title="H & D Dienstleistungen SRL" rel="stylesheet" type="text/css" href="/css/language_selector.css" media="screen">   
    
    <link rel="icon" href="/images/icon/favicon.ico" type="image/x-icon">
    <?php
    if(isset($_REQUEST['language'])) {
      # Vorrang - das ist das Sprachwahlmenue
      $language = $_REQUEST['language'];
    } elseif(isset($_SESSION['language'])){
      $language = $_SESSION['language'];
    } elseif(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
      # das hier ist die Sprache, die der Browser vorrangig unterstuetzt
      $language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
    } else {
      # allerletzte Moeglichkeit, wenn gar nichts greift, dann immer deutsch
      $language = "de";
    }
    $_SESSION['language'] = $language;

    echo '<!-- Debug: LanguageSelector geladen -->';

?>
 
  
</head>

<body>

<header>
<?php
 
 
$role = isset($_SESSION['admin_a']) ? (int) $_SESSION['admin_a'] : null;
$language = 'de'; // Falls die Sprache nicht aus der Session kommt, setze hier die gewünschte Standardsprache.

$stmt = $main_db_connection->prepare("SELECT * FROM header WHERE role = ? AND language = ? LIMIT 1");
$stmt->bind_param("is", $role, $language);
$stmt->execute();
$header_result = $stmt->get_result();

if ($header_result->num_rows > 0) {
    // Rolle hat einen spezifischen Header
    $rec = $header_result->fetch_assoc();
} else {
    // Kein spezifischer Header -> Nutze den allgemeinen Header (role = NULL)
    $stmt = $main_db_connection->prepare("SELECT * FROM header WHERE role IS NULL AND language = ? LIMIT 1");
    $stmt->bind_param("s", $language);
    $stmt->execute();
    $header_result = $stmt->get_result();
    
    if ($header_result->num_rows > 0) {
        $rec = $header_result->fetch_assoc();
    } else {
        $rec = null; // Falls gar kein Header existiert
    }
}

if ($rec) {
    echo '<div class="' . htmlspecialchars($rec['css']) . '">
        <a title="' . htmlspecialchars($rec['label']) . '" href="' . htmlspecialchars($rec['link']) . '">
        <img class="logo" src="' . htmlspecialchars($rec['images'], ENT_QUOTES, 'UTF-8') . '" alt="logo">
        <a class="companyname" title="' . htmlspecialchars($rec['label']) . '" href="' . htmlspecialchars($rec['link']) . '">'
        . htmlspecialchars($rec['text']) . '</a></div>';
} else {
    error_log("❌ Kein Header gefunden.");
}

#$stmt->close();
 include $_SERVER['DOCUMENT_ROOT'] . '/function/language_selector.inc.php'
 ?>

    <navi>
    <div id="Navigation">
        <?php
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/function//navi.inc.php')) {
            error_log("❌ Navi-Datei nicht gefunden!");
        } else {
            error_log("✅ Navi-Datei gefunden. Wird geladen...");
        }
        include $_SERVER['DOCUMENT_ROOT'] . '/function/navi.inc.php';
        ?>
        
    </div>
</navi>

</header>

<main>
<div class="content">  
<?php
// Verbindung zur Datenbank herstellen
 

if ($main_db_connection->connect_error) {
    die("Verbindung fehlgeschlagen: " . $main_db_connection->connect_error);
}

// Startseite ermitteln, falls kein 'page'-Parameter gesetzt ist
if (!isset($_REQUEST['page'])) {
    error_log("⚠️ WARNUNG: Kein 'page'-Parameter in der URL, versuche Startseite zu laden.");
    $startseite_result = $main_db_connection->query(
        "SELECT UNIX_TIMESTAMP(id) AS ts FROM page WHERE fk_translation_placeholder='PAGE_START_LABEL' LIMIT 1"
    );

    if (!$startseite_result) {
        die("Datenbankfehler: " . $main_db_connection->error);
    }

    $page = $startseite_result->fetch_assoc();
    if (!$page) {
        die("⚠️ Keine Startseite gefunden! Prüfe die 'page'-Tabelle.");
    }

    $_REQUEST['page'] = $page['ts'];
    error_log("✅ Startseite gesetzt: " . $_REQUEST['page']);
}

$page_output_all = [];
$plugin_content_id = "";

// SQL-Abfrage, um Plugins für die aktuelle Seite zu laden
$stmt = $main_db_connection->prepare("    
    SELECT 
        *, 
        plugin.name AS plugin_label, 
        UNIX_TIMESTAMP(page.id) AS page_id, 
        page.id AS page_raw_id 
    FROM page_config 
    JOIN page ON page_config.fk_page_id = page.id 
    JOIN plugin ON page_config.fk_plugin_id = plugin.id 
    WHERE UNIX_TIMESTAMP(page.id) = ? 
    ORDER BY page_config.idx ASC
");

$stmt->bind_param('i', $_REQUEST['page']);
$stmt->execute();
$result = $stmt->get_result();

while ($record = $result->fetch_assoc()) {
    $print_all = false;

    if ($record['print_all'] == 1) {
        $page_plugin = $record['page_id'] . '_' . $record['plugin_label'];
        if (in_array($page_plugin, $page_output_all)) {
            continue;
        } else {
            $page_output_all[] = $page_plugin;
            $print_all = true;
        }
    }

    $plugin_content_id = $record['plugin_content_id'];

    // Definiere eine Liste möglicher Verzeichnisse
    $plugin_paths = [
        $_SERVER['DOCUMENT_ROOT'] . '/plugin/',         // Standardverzeichnis
        $_SERVER['DOCUMENT_ROOT'] . '/plugin/admin_plugin/',  
        $_SERVER['DOCUMENT_ROOT'] . '/plugin/plugin_login/',  
        $_SERVER['DOCUMENT_ROOT'] . '/plugin/plugin_member/',  
        $_SERVER['DOCUMENT_ROOT'] . '/plugin/plugin_cards/',  
        $_SERVER['DOCUMENT_ROOT'] . '/plugin/extra_plugin/'  
    ];

    $plugin_found = false;

    foreach ($plugin_paths as $path) {
        $plugin_file = $path . 'plugin_' . $record['plugin_label'] . '.php';

        if (file_exists($plugin_file)) {
            include $plugin_file;
            $plugin_found = true;
            break; // Sobald das Plugin gefunden wurde, beende die Schleife
        }
    }

    // Falls das Plugin nicht gefunden wurde, eine Fehlermeldung ausgeben
    if (!$plugin_found) {
        echo "Fehler: Plugin '" . $record['plugin_label'] . "' nicht gefunden!";
    }
}

// Datenbankverbindung schließen
$stmt->close();

?>


</div> <!-- Schließt .content -->
</main> <!-- Schließt main -->
<footer>
     
    <?php
    $stmt = $main_db_connection->prepare('SELECT headline, link, label, css FROM footer LIMIT 1');
    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($headline, $link, $label, $css);
        if ($stmt->fetch()) {
            echo '© 2020 - ' . date("Y") . ' <a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($label) . '</a>';
        }
        $stmt->close();
    } else {
        error_log("❌ Fehler beim Laden des Footers.");
    }
    ?> 
    
</footer>
 
</body>
</html>
