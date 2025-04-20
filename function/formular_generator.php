<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";

$connection = getDbConnection();

$formulare = $connection->query("SELECT * FROM p_content_formular ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$felder = $connection->query("SELECT * FROM p_content_formular_field ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

function sanitize_name($label) {
    return strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $label));
}

function generate_form_html($fields, $formular_id) {
    $html = "<form method=\"post\" action=\"eintragen.php\">\n";
    foreach ($fields as $feld) {
        if ((string)$feld['fk_formular_id'] !== (string)$formular_id) continue;
        $name = sanitize_name($feld['label']);
        $type = htmlspecialchars($feld['type']);
        $label = htmlspecialchars($feld['label']);

        $html .= "<label>$label</label>\n";
        $html .= "<input type=\"$type\" name=\"$name\">\n<br><br>\n";
    }
    $html .= "<input type=\"submit\" value=\"Absenden\">\n</form>";
    return $html;
}

// Wenn Button gedrückt wurde, speichere das generierte HTML
if (isset($_POST['generate_html']) && !empty($_POST['formular_id'])) {
    $formular_id = $_POST['formular_id'];
    $formular = null;
    foreach ($formulare as $f) {
        if ((string)$f['id'] === (string)$formular_id) {
            $formular = $f;
            break;
        }
    }

    if ($formular) {
        $html_code = generate_form_html($felder, $formular_id);
        $folder = '';

        foreach ($felder as $feld) {
            if ((string)$feld['fk_formular_id'] === (string)$formular_id && !empty($feld['folder'])) {
                $folder = $feld['folder'];
                break;
            }
        }

        if (!empty($folder)) {
            $file_name = "formular_" . sanitize_name($formular['label']) . ".html";
            $file_path = $_SERVER['DOCUMENT_ROOT'] . "/$folder/$file_name";

            // Ordner prüfen und ggf. erstellen
            if (!is_dir($_SERVER['DOCUMENT_ROOT'] . "/$folder")) {
                mkdir($_SERVER['DOCUMENT_ROOT'] . "/$folder", 0777, true);
            }

            if (file_put_contents($file_path, $html_code)) {
                echo "<div style='color:green;'>Formular gespeichert unter: $file_path</div>";
            } else {
                echo "<div style='color:red;'>Fehler beim Speichern der Datei.</div>";
            }
        } else {
            echo "<div style='color:red;'>Kein Speicherpfad im Feld 'folder' angegeben.</div>";
        }
    }
}
?>
