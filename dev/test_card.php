<?php
include $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";

$main_db_connection = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type'])) {
    $type = $_POST['type'];
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;
    $headline = $_POST['headline'] ?? '';
    $text = $_POST['text'] ?? '';
    $link = $_POST['link'] ?? '';
    $label = $_POST['label'] ?? '';
    $fk_cardstack_id = $_POST['fk_cardstack_id'] ?? null;
    $fk_card_id = $_POST['fk_card_id'] ?? null;

    if (in_array($type, ['p_card_content', 'p_card_stack', 'p_card_editor'])) {
        if ($action === 'save') {
            // Speichern der Daten
            if (empty($id)) {
                // INSERT
                if ($type === 'p_card_content') {
                    if ($fk_card_id !== null && $fk_card_id > 0) {
                        // Überprüfen, ob fk_card_id in p_card_editor existiert
                        $check_stmt = $main_db_connection->prepare("SELECT id FROM p_card_editor WHERE id = ?");
                        $check_stmt->bind_param('i', $fk_card_id);
                        $check_stmt->execute();
                        $check_stmt->store_result();

                        if ($check_stmt->num_rows === 0) {
                            die("fk_card_id: Der Wert existiert nicht in der Tabelle p_card_editor.");
                        }

                        // INSERT in p_card_content
                        $stmt = $main_db_connection->prepare("INSERT INTO p_card_content (fk_card_id, headline, text, link) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param('isss', $fk_card_id, $headline, $text, $link);
                    } else {
                        // Wenn fk_card_id null oder <= 0, dann INSERT ohne fk_card_id
                        $stmt = $main_db_connection->prepare("INSERT INTO p_card_content (headline, text, link) VALUES (?, ?, ?)");
                        $stmt->bind_param('sss', $headline, $text, $link);
                    }
                } elseif ($type === 'p_card_stack') {
                    // INSERT in p_card_stack
                    $stmt = $main_db_connection->prepare("INSERT INTO p_card_stack (label) VALUES (?)");
                    $stmt->bind_param('s', $label);
                } elseif ($type === 'p_card_editor') {
                    // Überprüfen, ob fk_cardstack_id in p_card_stack existiert
                    if ($fk_cardstack_id !== null && $fk_cardstack_id > 0) {
                        $check_stmt = $main_db_connection->prepare("SELECT id FROM p_card_stack WHERE id = ?");
                        $check_stmt->bind_param('i', $fk_cardstack_id);
                        $check_stmt->execute();
                        $check_stmt->store_result();

                        // Wenn kein Datensatz mit dieser fk_cardstack_id existiert
                        if ($check_stmt->num_rows === 0) {
                            die("fk_cardstack_id: Der Wert existiert nicht in der Tabelle p_card_stack.");
                        }

                        // INSERT in p_card_editor
                        $stmt = $main_db_connection->prepare("INSERT INTO p_card_editor (fk_cardstack_id, label) VALUES (?, ?)");
                        $stmt->bind_param('is', $fk_cardstack_id, $label);
                    } else {
                        // Wenn fk_cardstack_id null oder <= 0, dann INSERT ohne fk_cardstack_id
                        $stmt = $main_db_connection->prepare("INSERT INTO p_card_editor (label) VALUES (?)");
                        $stmt->bind_param('s', $label);
                    }
                }
            } else {
                // UPDATE
                if ($type === 'p_card_content') {
                    if ($fk_card_id !== null && $fk_card_id > 0) {
                        $stmt = $main_db_connection->prepare("UPDATE p_card_content SET fk_card_id=?, headline=?, text=?, link=? WHERE id=?");
                        $stmt->bind_param('isssi', $fk_card_id, $headline, $text, $link, $id);
                    } else {
                        $stmt = $main_db_connection->prepare("UPDATE p_card_content SET headline=?, text=?, link=? WHERE id=?");
                        $stmt->bind_param('sssi', $headline, $text, $link, $id);
                    }
                } elseif ($type === 'p_card_stack') {
                    $stmt = $main_db_connection->prepare("UPDATE p_card_stack SET label=? WHERE id=?");
                    $stmt->bind_param('si', $label, $id);
                } elseif ($type === 'p_card_editor') {
                    if ($fk_cardstack_id !== null && $fk_cardstack_id > 0) {
                        $stmt = $main_db_connection->prepare("UPDATE p_card_editor SET fk_cardstack_id=?, label=? WHERE id=?");
                        $stmt->bind_param('isi', $fk_cardstack_id, $label, $id);
                    } else {
                        $stmt = $main_db_connection->prepare("UPDATE p_card_editor SET label=? WHERE id=?");
                        $stmt->bind_param('si', $label, $id);
                    }
                }
            }
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'delete' && $id) {
            // Sicherstellen, dass die ID für den Löschvorgang existiert
            $stmt = $main_db_connection->prepare("SELECT id FROM $type WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                // Der Datensatz existiert, also löschen
                $delete_stmt = $main_db_connection->prepare("DELETE FROM $type WHERE id = ?");
                $delete_stmt->bind_param('i', $id);
                $delete_stmt->execute();

                if ($delete_stmt->affected_rows > 0) {
                    echo "Der Datensatz wurde erfolgreich gelöscht.";
                } else {
                    echo "Fehler beim Löschen des Datensatzes.";
                }
                $delete_stmt->close();
            } else {
                echo "Der Datensatz existiert nicht oder die ID ist ungültig.";
            }
            $stmt->close();
        }
    }
}

// Daten laden
$card_stacks = $main_db_connection->query("SELECT * FROM p_card_stack ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$card_editors = $main_db_connection->query("SELECT * FROM p_card_editor ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$card_contents = $main_db_connection->query("SELECT * FROM p_card_content ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

function getSelected($data, $id) {
    foreach ($data as $item) {
        if ($item['id'] == $id) {
            return $item;
        }
    }
    return null;
}

$selected_card_stack = getSelected($card_stacks, $_POST['id'] ?? 0);
$selected_card_editor = getSelected($card_editors, $_POST['id'] ?? 0);
$selected_card_content = getSelected($card_contents, $_POST['id'] ?? 0);
?>

<!-- Deine HTML-Formulare und das Layout hier -->

<div class="admin_container">
    <!-- Cardstack Verwaltung -->
    <div class="admin_box">
        <h2>Cardstack bearbeiten</h2>
        <form method="post">
            <input type="hidden" name="type" value="p_card_stack">
            <label>Cardstack auswählen:</label>
            <select name="id" onchange="this.form.submit()">
                <option value="0">Neuer Cardstack</option>
                <?php foreach ($card_stacks as $stack): ?>
                    <option value="<?= $stack['id'] ?>" <?= ($selected_card_stack['id'] ?? 0) == $stack['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($stack['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Label:</label>
            <input type="text" name="label" value="<?= htmlspecialchars($selected_card_stack['label'] ?? '') ?>">
            <div class="buttons">
                <button type="submit" name="action" value="save">Speichern</button>
                <button type="submit" name="action" value="delete">Löschen</button>
            </div>
        </form>
    </div>

    <!-- Card Editor Verwaltung -->
    <div class="admin_box">
        <h2>Card Editor bearbeiten</h2>
        <form method="post">
            <input type="hidden" name="type" value="p_card_editor">
            <label>Cardstack auswählen:</label>
            <select name="fk_cardstack_id">
                <option value="0">Keine Cardstack zuweisen</option>
                <?php foreach ($card_stacks as $stack): ?>
                    <option value="<?= $stack['id'] ?>" <?= ($selected_card_editor['fk_cardstack_id'] ?? 0) == $stack['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($stack['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Label:</label>
            <input type="text" name="label" value="<?= htmlspecialchars($selected_card_editor['label'] ?? '') ?>">
            <div class="buttons">
                <button type="submit" name="action" value="save">Speichern</button>
                <button type="submit" name="action" value="delete">Löschen</button>
            </div>
        </form>
    </div>

    <!-- Card Content Verwaltung -->
    <div class="admin_box">
        <h2>Card Content bearbeiten</h2>
        <form method="post">
            <input type="hidden" name="type" value="p_card_content">
            <label>Card auswählen:</label>
            <select name="fk_card_id">
                <option value="0">Neue Karte</option>
                <?php foreach ($card_editors as $editor): ?>
                    <option value="<?= $editor['id'] ?>" <?= ($selected_card_content['fk_card_id'] ?? 0) == $editor['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($editor['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Headline:</label>
            <input type="text" name="headline" value="<?= htmlspecialchars($selected_card_content['headline'] ?? '') ?>">
            <label>Text:</label>
            <input type="text" name="text" value="<?= htmlspecialchars($selected_card_content['text'] ?? '') ?>">
            <label>Link:</label>
            <input type="text" name="link" value="<?= htmlspecialchars($selected_card_content['link'] ?? '') ?>">
            <div class="buttons">
                <button type="submit" name="action" value="save">Speichern</button>
                <button type="submit" name="action" value="delete">Löschen</button>
            </div>
        </form>
    </div>
</div>

<style>
.admin_container { display: flex; flex-wrap: wrap; gap: 20px; }
.admin_box { width: 30%; padding: 20px; border: 1px solid #ccc; border-radius: 5px; }
label { display: block; margin-top: 10px; }
input, select, textarea { width: 100%; padding: 5px; margin-top: 5px; }
.buttons { margin-top: 10px; }
</style>