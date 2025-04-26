<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_a'])) {
	header('Location:/index.php');
}
 
$connection = getDbConnection();

$action = $_POST['action'] ?? '';
$type = $_POST['type'] ?? '';
$id = $_POST['id'] ?? '';
$label = $_POST['label'] ?? '';
$headline = $_POST['headline'] ?? '';
$text = $_POST['text'] ?? '';
$link = $_POST['link'] ?? '';
$fk_cardstack_id = $_POST['fk_cardstack_id'] ?? '';
$fk_card_id = $_POST['fk_card_id'] ?? '';

// SPEICHERN oder LÖSCHEN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $type) {
    if ($action === 'save') {
        if ($type === 'p_card_stack') {
            if ($id) {
                $stmt = $connection->prepare("UPDATE p_card_stack SET label=? WHERE id=?");
                $stmt->bind_param('ss', $label, $id);
            } else {
                $stmt = $connection->prepare("INSERT INTO p_card_stack (label) VALUES (?)");
                $stmt->bind_param('s', $label);
            }
        } elseif ($type === 'p_card_editor') {
            if ($id) {
                $stmt = $connection->prepare("UPDATE p_card_editor SET fk_cardstack_id=?, label=? WHERE id=?");
                $stmt->bind_param('sss', $fk_cardstack_id, $label, $id);
            } else {
                $stmt = $connection->prepare("INSERT INTO p_card_editor (fk_cardstack_id, label) VALUES (?, ?)");
                $stmt->bind_param('ss', $fk_cardstack_id, $label);
            }
        } elseif ($type === 'p_card_content') {
            // Die CardStack-ID wird als fk_card_id gespeichert, wenn kein Editor ausgewählt wurde
            if (empty($fk_card_id) && !empty($fk_cardstack_id)) {
                // Wähle einen passenden Editor aus dem Stack
                foreach ($card_editors as $editor) {
                    if ($editor['fk_cardstack_id'] === $fk_cardstack_id) {
                        $fk_card_id = $editor['id'];
                        break;
                    }
                }
            }

            if (empty($fk_card_id)) {
                die("Fehler: Kein gültiger fk_card_id-Wert gesetzt!");
            }

            if ($id) {
                $stmt = $connection->prepare("UPDATE p_card_content SET fk_card_id=?, headline=?, text=?, link=? WHERE id=?");
                $stmt->bind_param('sssss', $fk_card_id, $headline, $text, $link, $id);
            } else {
                $stmt = $connection->prepare("INSERT INTO p_card_content (fk_card_id, headline, text, link) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('ssss', $fk_card_id, $headline, $text, $link);
            }
        }
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'delete' && $id) {
        $stmt = $connection->prepare("DELETE FROM $type WHERE id = ?");
        $stmt->bind_param('s', $id);
        $stmt->execute();
        #$stmt->close();
        $_POST = []; // Reset Auswahl
    }
}

// DATEN LADEN
$card_stacks = $connection->query("SELECT * FROM p_card_stack ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$card_editors = $connection->query("
    SELECT e.* 
    FROM p_card_editor e 
    JOIN p_card_stack s ON e.fk_cardstack_id = s.id 
    ORDER BY e.fk_cardstack_id DESC
")->fetch_all(MYSQLI_ASSOC);
$card_contents = $connection->query("
    SELECT c.*, e.label AS editor_label 
    FROM p_card_content c 
    LEFT JOIN p_card_editor e ON c.fk_card_id = e.id 
    ORDER BY c.id DESC
")->fetch_all(MYSQLI_ASSOC);

function findSelected($list, $id) {
    foreach ($list as $entry) {
        if ($entry['id'] === $id) return $entry;
    }
    return [];
}

function getCardStackIdByEditor($editor_id, $editors) {
    foreach ($editors as $editor) {
        if ($editor['id'] === $editor_id) {
            return $editor['fk_cardstack_id'];
        }
    }
    return '';
}

$selected_stack = findSelected($card_stacks, $_POST['fk_cardstack_id'] ?? '');
$selected_editor = findSelected($card_editors, $_POST['id'] ?? '');
$selected_content = findSelected($card_contents, $_POST['id'] ?? '');

$fk_cardstack_id = isset($selected_content['fk_card_id']) && $selected_content['fk_card_id'] !== '' 
    ? getCardStackIdByEditor($selected_content['fk_card_id'], $card_editors) 
    : '';

$filtered_editors = $fk_cardstack_id
    ? array_filter($card_editors, function($editor) use ($fk_cardstack_id) {
        return $editor['fk_cardstack_id'] === $fk_cardstack_id;
    })
    : $card_editors;
?>

<!-- HTML-FORMULARE -->
<div class="admin_container">

    <!-- Card Stack -->
    <div class="admin_box">
        <h2>Card Stack</h2>
        <form method="post">
            <input type="hidden" name="type" value="p_card_stack">
            <label>Auswahl:</label>
            <select name="id" onchange="this.form.submit()">
                <option value="">Neuer Stack</option>
                <?php foreach ($card_stacks as $stack): ?>
                    <option value="<?= $stack['id'] ?>" <?= ($selected_stack['id'] ?? '') === $stack['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($stack['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Label:</label>
            <input type="text" name="label" value="<?= htmlspecialchars($selected_stack['label'] ?? '') ?>">
            <div class="buttons">
                <button class="button-save" name="action" value="save">Speichern</button>
                <button class="button-delete" name="action" value="delete">Löschen</button>
            </div>
        </form>
    </div>

<!-- Card Editor -->
<div class="admin_box">
    <h2>Card Editor</h2>
    <form method="post">
        <input type="hidden" name="type" value="p_card_editor">
        <input type="hidden" name="id" value="<?= $selected_editor['id'] ?? '' ?>">

        <label>Cardstack auswählen:</label>
        <select name="fk_cardstack_id">
            <option value="">-- auswählen --</option>
            <?php foreach ($card_stacks as $stack): ?>
                <option value="<?= $stack['id'] ?>" <?= ($selected_editor['fk_cardstack_id'] ?? '') === $stack['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($stack['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Label:</label>
        <input type="text" name="label" value="<?= htmlspecialchars($selected_editor['label'] ?? '') ?>">

         
        <div class="buttons">
            <button class="button-save" name="action" value="save">Speichern</button>
            <button class="button-delete" name="action" value="delete">Löschen</button>
        </div>
    </form>
</div>

<div class="admin_box">
    <h2>Card Content</h2>
    <form method="post">
        <input type="hidden" name="type" value="p_card_content">
        <input type="hidden" name="id" value="<?= $selected_content['id'] ?? '' ?>">
        <input type="hidden" name="fk_cardstack_id" value="<?= htmlspecialchars($fk_cardstack_id ?? '') ?>">

        <!-- Auswahl des Editors -->
        <label>Editor auswählen:</label>
        <select name="fk_card_id">
            <option value="">-- wählen --</option>
            <?php
                foreach ($filtered_editors as $editor): ?>
                <option value="<?= $editor['id'] ?>" <?= ($selected_content['fk_card_id'] ?? '') === $editor['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($editor['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Headline, Text und Link -->
        <label>Headline:</label>
        <input type="text" name="headline" value="<?= htmlspecialchars($selected_content['headline'] ?? '') ?>">

        <label>Text:</label>
        <input type="text" name="text" value="<?= htmlspecialchars($selected_content['text'] ?? '') ?>">

        <label>Link:</label>
        <input type="text" name="link" value="<?= htmlspecialchars($selected_content['link'] ?? '') ?>">

        <div class="buttons">
             <button class="button-save" name="action" value="save">Speichern</button>
             <button class="button-delete" name="action" value="delete">Löschen</button>
        </div>

        </div>
    </form>
</div>

 