<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
include_once $_SERVER['DOCUMENT_ROOT'] . "/plugin/extra_plugin/formular_generator.php";

$connection = getDbConnection();

$action = $_POST['action'] ?? '';
$type = $_POST['type'] ?? '';
$id = $_POST['id'] ?? '';
$fk_formular_id = $_POST['fk_formular_id'] ?? ($_POST['id'] ?? '');
$type_field = $_POST['type_field'] ?? '';
$label = $_POST['label'] ?? '';
$column = $_POST['column'] ?? '';
$row = $_POST['row'] ?? '';
$label_enabled = $_POST['label_enabled'] ?? '';
$folder = $_POST['folder'] ?? '';

// Formular speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($type === 'formular') {
        if ($action === 'save') {
            if ($id) {
                $stmt = $connection->prepare("UPDATE p_content_formular SET label=?, columns=?, use_placeholder=?, use_extra_label=? WHERE id=?");
                $stmt->bind_param("siiis", $label, $column, $row, $label_enabled, $id);
            } else {
                $stmt = $connection->prepare("INSERT INTO p_content_formular (label, columns, use_placeholder, use_extra_label) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("siii", $label, $column, $row, $label_enabled);
            }
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'delete' && $id) {
            $stmt = $connection->prepare("DELETE FROM p_content_formular WHERE id=?");
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $stmt->close();
        }
    }
    if ($type === 'field') {
        if ($action === 'save') {
            if (empty($fk_formular_id)) {
                echo "<div style='color:red;'>Fehler: Kein gültiger fk_formular_id-Wert gesetzt!</div>";
            } else {
                if ($id) {
                    $stmt = $connection->prepare("UPDATE p_content_formular_field 
                        SET fk_formular_id=?, type=?, label=?, `column`=?, `row`=?, label_enabled=?, folder=? 
                        WHERE id=?");
                    $stmt->bind_param("ssssiiis", $fk_formular_id, $type_field, $label, $column, $row, $label_enabled, $folder, $id);
                } else {
                    $stmt = $connection->prepare("INSERT INTO p_content_formular_field 
                        (fk_formular_id, type, label, `column`, `row`, label_enabled, folder) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssiis", $fk_formular_id, $type_field, $label, $column, $row, $label_enabled, $folder);
                }
                $stmt->execute();
                $stmt->close();
            }
        } elseif ($action === 'delete' && $id) {
            $stmt = $connection->prepare("DELETE FROM p_content_formular_field WHERE id=?");
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$formulare = $connection->query("SELECT * FROM p_content_formular ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$felder = $connection->query("SELECT * FROM p_content_formular_field ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

function getSelected($list, $id) {
    foreach ($list as $item) {
        if ($item['id'] === $id) return $item;
    }
    return [];
}

$selected_formular = getSelected($formulare, $_POST['id'] ?? '');
$selected_field = getSelected($felder, $_POST['id'] ?? '');
?>

<div class="admin_container">
    <div class="admin_box">
        <h2>Formular</h2>
        <form method="post">
            <input type="hidden" name="type" value="formular">
            <input type="hidden" name="id" value="<?= $selected_formular['id'] ?? '' ?>">
            <label>Formular auswählen:</label>
            <select name="id" onchange="this.form.submit()">
                <option value="">Neues Formular</option>
                <?php foreach ($formulare as $form): ?>
                    <option value="<?= $form['id'] ?>" <?= ($selected_formular['id'] ?? '') === $form['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($form['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Label:</label>
            <input type="text" name="label" value="<?= htmlspecialchars($selected_formular['label'] ?? '') ?>">
            <label>Spalten:</label>
            <input type="number" name="column" value="<?= $selected_formular['columns'] ?? 1 ?>">
            <label>Placeholder:</label>
            <input type="number" name="row" value="<?= $selected_formular['use_placeholder'] ?? 0 ?>">
            <label>Extra Label:</label>
            <input type="number" name="label_enabled" value="<?= $selected_formular['use_extra_label'] ?? 0 ?>">
            <div class="buttons">
                <button name="action" value="save">Speichern</button>
                <button name="action" value="delete">Löschen</button>
            </div>
        </form>
    </div>
</div>

<style>
.admin_container { display: flex; flex-wrap: wrap; gap: 20px; }
.admin_box { width: 30%; padding: 20px; border: 1px solid #ccc; border-radius: 5px; }
label { display: block; margin-top: 10px; }
input, select { width: 100%; padding: 6px; margin-top: 4px; }
.buttons { margin-top: 10px; }
</style>

