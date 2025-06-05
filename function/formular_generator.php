<?php
// Datei: /dev/formular_generator_function.php

include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
$connection = getDbConnection();

function generateAndSaveFormular(string $formular_id, bool $output_to_browser = true): void
{
    global $connection;

    try {
        // Überprüfen, ob die Verbindung zur Datenbank aktiv ist
        if (!$connection->connect_errno) {
            // 1. Formular-Metadaten laden
            $form_stmt = $connection->prepare("SELECT * FROM p_content_formular WHERE id = ?");
            $form_stmt->bind_param("s", $formular_id);
            $form_stmt->execute();
            $form = $form_stmt->get_result()->fetch_assoc();

            // 2. Felder laden
            $field_stmt = $connection->prepare("SELECT * FROM p_content_formular_field WHERE fk_formular_id = ?");
            $field_stmt->bind_param("s", $formular_id);
            $field_stmt->execute();
            $fields = $field_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            // 3. Neue Tabelle anlegen
            $table_name = 'generated_form_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $formular_id);
            $columns_sql = [];
            $used_column_names = []; // Um die verwendeten Spaltennamen zu verfolgen

            foreach ($fields as $field) {
                if (empty($field['label'])) {
                    continue; // Feld überspringen, wenn kein Label gesetzt ist
                }

                $column_name = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $field['label']));

                // Stelle sicher, dass der Spaltenname eindeutig ist
                $original_column_name = $column_name;
                $counter = 1;
                while (in_array($column_name, $used_column_names)) {
                    $column_name = $original_column_name . '_' . $counter;
                    $counter++;
                }

                $used_column_names[] = $column_name; // Spaltennamen merken

                // Typ der Spalte basierend auf dem Feldtyp festlegen
                $type = match ($field['type']) {
                    'text', 'email', 'form', 'action' => 'VARCHAR(255)',
                    'number' => 'INT',
                    'checkbox' => 'TINYINT(1)',
                    'textarea' => 'TEXT', // Für textarea Felder
                    'select' => 'TEXT', // Für select Felder
                    default => 'TEXT',
                };

                // Spalte zur Liste hinzufügen
                $columns_sql[] = "`$column_name` $type";
            }

            $columns_sql[] = "`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";

            // SQL zum Erstellen der Tabelle
            $create_sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                " . implode(",\n    ", $columns_sql) . "
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

            $connection->query($create_sql);

            // 4. HTML erzeugen
            $css_class = $form['css_form'] ?: 'formular_wrapper';
            $html_output = "<div class=\"$css_class\">\n";
            #$html_output .= "<form method=\"post\" action=\"eintragen.php\">\n";

            foreach ($fields as $field) {
                $name = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $field['label']));
                $label = htmlspecialchars($field['label']);
                $input_type = match ($field['type']) {
                    'email', 'number', 'checkbox', 'text' => $field['type'],
                    default => 'text',
                };
                $html_output .= "    <label>$label</label>\n";
                $html_output .= "    <input type='$input_type' name='$name'>\n    <br><br>\n";
            }

            $html_output .= "    <input type=\"submit\" value=\"Absenden\">\n";
            $html_output .= "</form>\n</div>\n";

            // 5. Datei speichern
            $folder_path = '';
            foreach ($fields as $field) {
                if (!empty($field['folder'])) {
                    $folder_path = trim($field['folder'], '/');
                    break;
                }
            }

            if (!empty($folder_path)) {
                $base_path = $_SERVER['DOCUMENT_ROOT'] . "/" . $folder_path;
                if (!is_dir($base_path)) {
                    if (!mkdir($base_path, 0777, true)) {
                        echo "<div style='color:red;'>Ordner konnte nicht erstellt werden: $base_path</div>";
                        return;
                    }
                }

                $filename = "formular_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $form['label']) . ".html";
                $file_path = "$base_path/$filename";

                if (file_put_contents($file_path, $html_output) === false) {
                    echo "<div style='color:red;'>Fehler beim Speichern der Datei: $file_path</div>";
                } else {
                    if ($output_to_browser) {
                        echo "<div style='color:green;'>Formular wurde gespeichert unter: <code>$file_path</code></div>\n";
                        echo $html_output;
                    }
                }
            } elseif ($output_to_browser) {
                echo "<div style='color:orange;'>Hinweis: Kein Speicherpfad (folder) gesetzt – Formular wurde nicht gespeichert.</div>\n";
                // echo $html_output; // Doppelte Ausgabe entfernt
            }
        } else {
            throw new Exception("Datenbankverbindung ist nicht aktiv.");
        }
    } catch (Exception $e) {
        echo "<div style='color:red;'>Fehler: " . $e->getMessage() . "</div>";
    }
}
?>
