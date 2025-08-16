<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/function/formular_generator.php";

$connection = getDbConnection();

$action = $_POST['action'] ?? '';
$type = $_POST['type'] ?? '';
$id = $_POST['id'] ?? '';
$fk_formular_id = $_POST['fk_formular_id'] ?? ($_POST['id'] ?? '');
$type_field = $_POST['type_field'] ?? '';
$label = $_POST['label'] ?? '';
$form_role = $_POST['form_role'] ?? '';
if ($form_role === '') {
    $form_role = 0;
}
$column = $_POST['column'] ?? '';
$row = $_POST['row'] ?? '';
$label_enabled = $_POST['label_enabled'] ?? '';
$folder = $_POST['folder'] ?? '';
$css_form = $_POST['css_form'] ?? '';

// Formular speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($type === 'formular') {
            if ($action === 'save') {
                if ($id) {
                    $stmt = $connection->prepare("UPDATE p_content_formular SET label=?, css_form=?, columns=?, use_placeholder=?, use_extra_label=? WHERE id=?");
                    $stmt->bind_param("ssiiis", $label, $css_form, $column, $row, $label_enabled, $id);
                } else {
                    $stmt = $connection->prepare("INSERT INTO p_content_formular (label, css_form, columns, use_placeholder, use_extra_label) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssiii", $label, $css_form, $column, $row, $label_enabled);
                }
                $stmt->execute();
                $stmt->close();
            } elseif ($action === 'delete' && $id) {
                $stmt = $connection->prepare("DELETE FROM p_content_formular WHERE id=?");
                $stmt->bind_param("s", $id);
                $stmt->execute();
            }
        }
        if ($type === 'field') {
            if ($action === 'save') {
                if (empty($fk_formular_id)) {
                    echo "<div style='color:red;'>Fehler: Kein gültiger fk_formular_id-Wert gesetzt!</div>";
                } else {
                    if ($id) {
                        $stmt = $connection->prepare("UPDATE p_content_formular_field 
                            SET fk_formular_id=?, type=?, label=?, form_role=?, `column`=?, `row`=?, label_enabled=?, folder=? 
                            WHERE id=?");
                        $stmt->bind_param("ssssiisis", $fk_formular_id, $type_field, $label, $form_role, $column, $row, $label_enabled, $folder, $id);
                    } else {
                        $stmt = $connection->prepare("INSERT INTO p_content_formular_field 
                            (fk_formular_id, type, label, form_role, `column`, `row`, label_enabled, folder) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssiiss", $fk_formular_id, $type_field, $label, $form_role, $column, $row, $label_enabled, $folder);
                    }
                    $stmt->execute();
                    $stmt->close();
                }
            } elseif ($action === 'delete' && $id) {
                $stmt = $connection->prepare("DELETE FROM p_content_formular_field WHERE id=?");
                $stmt->bind_param("s", $id);
                $stmt->execute();
            }
        }
    } catch (Exception $e) {
        echo "<div style='color:red;'>Fehler bei der Datenbankoperation: " . $e->getMessage() . "</div>";
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
            <label>css:</label>
            <input type="text" name="css_form" value="<?= htmlspecialchars($selected_formular['css_form'] ?? '') ?>">
            <label>Spalten:</label>
            <input type="number" name="column" value="<?= $selected_formular['columns'] ?? 1 ?>">
            <label>Placeholder:</label>
            <input type="number" name="row" value="<?= $selected_formular['use_placeholder'] ?? 0 ?>">
            <label>Extra Label:</label>
            <input type="number" name="label_enabled" value="<?= $selected_formular['use_extra_label'] ?? 0 ?>">
            <div class="buttons">
                <button class="button-save" name="action" value="save" title="Formular speichern">💾 Speichern</button>
                <button class="button-delete" name="action" value="delete" title="Formular löschen">🗑️ Löschen</button>
            </div>
        </form>
    </div>

    <div class="admin_box">
        <h2>Formular-Feld</h2>
        <form method="post">
            <input type="hidden" name="type" value="field">
            <input type="hidden" name="id" value="<?= $selected_field['id'] ?? '' ?>">
            <input type="hidden" name="fk_formular_id" value="<?= $selected_formular['id'] ?? '' ?>">
            <label>Feld auswählen:</label>
            <select name="id" onchange="this.form.submit()">
                <option value="">Neues Feld</option>
                <?php foreach ($felder as $field): ?>
                    <?php if ($field['fk_formular_id'] === $selected_formular['id']): ?>
                        <option value="<?= $field['id'] ?>" <?= ($selected_field['id'] ?? '') === $field['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($field['label']) ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <label>Typ:</label>
            <label>Label:</label>
            <input type="text" name="label" value="<?= htmlspecialchars($selected_field['label'] ?? '') ?>">
            <label>Role:</label>
            <input type="text" name="form_role" value="<?= htmlspecialchars($selected_field['form_role'] ?? '') ?>">
            <label>Spalte:</label>
            <input type="text" name="column" value="<?= htmlspecialchars($selected_field['column'] ?? '') ?>">
            <label>Reihe:</label>
            <input type="text" name="row" value="<?= htmlspecialchars($selected_field['row'] ?? '') ?>">
            <label>Label aktiv:</label>
            <input type="number" name="label_enabled" value="<?= $selected_field['label_enabled'] ?? 0 ?>">
            <label>Ordner:</label>
            <input type="text" name="folder" value="<?= htmlspecialchars($selected_field['folder'] ?? '') ?>">
             
            <div style="display: flex; gap: 20px; align-items: center; margin-top: 10px;">
                <label style="display: flex; align-items: center; gap: 5px;">
                    <input type="radio" name="type_field" value="textarea" <?= (isset($selected_field['type']) && $selected_field['type'] === 'textarea') ? 'checked' : '' ?>> Textarea
                </label>
                <label style="display: flex; align-items: center; gap: 5px;">
                    <input type="radio" name="type_field" value="select" <?= (isset($selected_field['type']) && $selected_field['type'] === 'select') ? 'checked' : '' ?>> Select
                </label>
            </div>
            <div class="buttons" style="margin-top: 20px; gap: 10px;">
                <button class="button-save" name="action" value="save" title="Formular speichern">💾 Speichern</button>
                <button class="button-delete" name="action" value="delete" title="Formular löschen">🗑️ Löschen</button>
            </div>
        </form>
    </div>
</div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_html'])) {
    $formular_id = $_POST['formular_id'] ?? '';
    $message = generateAndSaveFormular($formular_id, false, $connection);
    echo "<div style='color:green;'>Formular wurde gespeichert unter: <code>$message</code></div>";
}
?>

<div class="admin_box">
    <h2>HTML generieren &amp; speichern</h2>
    <form method="post">
        <label>Formular auswählen:</label>
        <select name="formular_id">
            <option value="">-- Formular wählen --</option>
            <?php foreach ($formulare as $form): ?>
                <option value="<?= $form['id'] ?>"><?= htmlspecialchars($form['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button-generate" type="submit" name="generate_html" title="HTML aus Formular generieren und speichern">⚙️ HTML generieren &amp; speichern</button>
    </form>
</div>


 </file>