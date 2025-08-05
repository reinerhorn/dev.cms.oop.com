<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/class/helper/SelectGenerator.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/class/helper/ButtonGenerator.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/class/security/auth_helpers.php";
requireAdmin();
 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class CardAdminManager {
    private mysqli $db;
    public array $cardStacks = [];
    public array $cardEditors = [];

    public function __construct(mysqli $db) {
        $this->db = $db;
        $this->loadData();
    }

    public function handlePost(array $post): void {
        $id = $post['id'] ?? '';
        $action = $post['action'] ?? '';
        $type = $post['type'] ?? '';
        $label = $post['label'] ?? '';
        $fk_cardstack_id = $post['fk_cardstack_id'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $type) {
            try {
                if ($action === 'save') {
                    if ($type === 'p_card_stack') {
                        if ($id) {
                            $stmt = $this->db->prepare("UPDATE p_card_stack SET label=? WHERE id=?");
                            $stmt->bind_param('ss', $label, $id);
                        } else {
                            $stmt = $this->db->prepare("INSERT INTO p_card_stack (label) VALUES (?)");
                            $stmt->bind_param('s', $label);
                        }
                    } elseif ($type === 'p_card_editor') {
                        if ($id) {
                            $stmt = $this->db->prepare("UPDATE p_card_editor SET fk_cardstack_id=?, label=? WHERE id=?");
                            $stmt->bind_param('sss', $fk_cardstack_id, $label, $id);
                        } else {
                            $stmt = $this->db->prepare("INSERT INTO p_card_editor (fk_cardstack_id, label) VALUES (?, ?)");
                            $stmt->bind_param('ss', $fk_cardstack_id, $label);
                        }
                        $stmt->execute();
                        $_POST = [];
                        $stmt->close();
                        return;
                    } elseif ($type === 'p_card_content') {
                        $headline = $post['headline'] ?? '';
                        $text = $post['text'] ?? '';
                        $link = $post['link'] ?? '';
                        $fk_card_id = $post['fk_card_id'] ?? '';

                        if ($id) {
                            $stmt = $this->db->prepare("UPDATE p_card_content SET headline=?, text=?, link=? WHERE id=?");
                            $stmt->bind_param('ssss', $headline, $text, $link, $id);
                        } else {
                            $id = uniqid('', true);
                            $stmt = $this->db->prepare("INSERT INTO p_card_content (id, fk_card_id, headline, text, link) VALUES (?, ?, ?, ?, ?)");
                            $stmt->bind_param('sssss', $id, $fk_card_id, $headline, $text, $link);
                        }
                        $stmt->execute();
                        $_POST = [];
                        $stmt->close();
                        return;
                    }
                    $stmt->execute();
                    $stmt->close();
                } elseif ($action === 'delete' && $id) {
                    $stmt = $this->db->prepare("DELETE FROM $type WHERE id = ?");
                    $stmt->bind_param('s', $id);
                    $stmt->execute();
                    $_POST = [];
                }
            } catch (Exception $e) {
                echo "<div style='color:red;'>Fehler bei der Datenbankoperation: " . $e->getMessage() . "</div>";
            }
        }
    }

    private function loadData(): void {
        $this->cardStacks = $this->db->query("SELECT * FROM p_card_stack ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
        $this->cardEditors = $this->db->query("
            SELECT e.id, e.fk_cardstack_id, e.label, s.label AS stack_label 
            FROM p_card_editor e 
            JOIN p_card_stack s ON e.fk_cardstack_id = s.id 
            ORDER BY e.fk_cardstack_id DESC
        ")->fetch_all(MYSQLI_ASSOC);
    }

    public function findSelected(array $list, $id): array {
        foreach ($list as $entry) {
            if ($entry['id'] === $id) return $entry;
        }
        return [];
    }

    public function getCardStackIdByEditor($editor_id): string {
        foreach ($this->cardEditors as $editor) {
            if ($editor['id'] === $editor_id) {
                return $editor['fk_cardstack_id'];
            }
        }
        return '';
    }
}

if (!isset($_SESSION['admin_a'])) {
    header('Location:/index.php');
}

$connection = getDbConnection();

$manager = new CardAdminManager($connection);
$manager->handlePost($_POST);

$selected_stack = [];
if (!empty($_POST['type']) && $_POST['type'] === 'p_card_stack' && !empty($_POST['id'])) {
    $stmt = $connection->prepare("SELECT * FROM p_card_stack WHERE id = ?");
    $stmt->bind_param("s", $_POST['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_stack = $result->fetch_assoc() ?: [];
    $stmt->close();
}

$card_stacks = $manager->cardStacks;
$card_editors = $manager->cardEditors;

$selected_editor = [];
if (!empty($_POST['type']) && $_POST['type'] === 'p_card_editor' && !empty($_POST['id'])) {
    $stmt = $connection->prepare("SELECT * FROM p_card_editor WHERE id = ?");
    $stmt->bind_param("s", $_POST['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_editor = $result->fetch_assoc() ?: [];
    $stmt->close();
}

$fk_cardstack_id = $selected_editor['fk_cardstack_id'] ?? '';

$selected_content = [];
if (!empty($_POST['type']) && $_POST['type'] === 'p_card_content' && !empty($_POST['fk_card_id'])) {
    $stmt = $connection->prepare("SELECT * FROM p_card_content WHERE fk_card_id = ?");
    $stmt->bind_param("s", $_POST['fk_card_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_content = $result->fetch_assoc() ?: [];
    $stmt->close();
}
?>

<!-- HTML-FORMULARE -->
<div class="admin_container">

    <!-- Card Stack -->
    <div class="admin_box">
        <h2>Card Stack</h2>
        <form method="post">
            <input type="hidden" name="type" value="p_card_stack">
            <label>Auswahl:</label>
            <?= SelectGenerator::render('id', $card_stacks, $selected_stack['id'] ?? '', 'Neuer Stack') ?>
            <label>Label:</label>
            <input type="text" name="label" value="<?= htmlspecialchars($selected_stack['label'] ?? '') ?>">
            <div class="buttons">
                
              <?= ButtonGenerator::render('action', 'save', 'Speichern', 'button-save') ?>
              <?= ButtonGenerator::render('action', 'delete', 'Löschen', 'button-delete', 'icon-trash') ?>
            </div>
        </form>
    </div>

   <!-- Card Editor -->
<div class="admin_box">
    <h2>Card Editor</h2>
    <form method="post">
        <input type="hidden" name="type" value="p_card_editor">
        <input type="hidden" name="id" value="<?= htmlspecialchars($selected_editor['id'] ?? '') ?>">

        <label>Card Editor auswählen:</label>
        <select name="id" onchange="this.form.submit()">
            <option value="">Editor auswählen</option>
            <?php foreach ($card_editors as $editor): ?>
                <option value="<?= htmlspecialchars($editor['id']) ?>" <?= ($editor['id'] === ($_POST['id'] ?? $selected_editor['id'] ?? '')) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($editor['label'] . ' (' . $editor['stack_label'] . ')') ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Show associated card stack as a hidden input and display -->
        <label>Card Stack auswählen:</label>
        <input type="hidden" name="fk_cardstack_id" value="<?= htmlspecialchars($selected_editor['fk_cardstack_id'] ?? '') ?>">
        <p><strong>Zugehöriger Stack:</strong> <?= htmlspecialchars($selected_editor['fk_cardstack_id'] ?? '—') ?></p>

        <label>Label:</label>
        <input type="text" name="label" value="<?= htmlspecialchars($selected_editor['label'] ?? '') ?>">


        <div class="buttons">
           <?= ButtonGenerator::render('action', 'save', 'Speichern', 'button-save') ?>
           <?= ButtonGenerator::render('action', 'delete', 'Löschen', 'button-delete') ?>
        </div>
    </form>
</div>
</div>
  <div class="admin_box">
        <h2>Card Content</h2>
        <form method="post">
            <input type="hidden" name="type" value="p_card_content">
            <input type="hidden" name="id" value="<?= $selected_content['id'] ?? '' ?>">
            <input type="hidden" name="fk_cardstack_id" value="<?= htmlspecialchars($fk_cardstack_id ?? '') ?>">

            <!-- Auswahl des Editors -->
            <?php
            $filtered_editors = $card_editors; // Optional: falls Filterung später benötigt wird
            ?>
            <label>Editor auswählen:</label>
            <select name="fk_card_id" onchange="this.form.submit()">
                <option value="">-- wählen --</option>
                <?php foreach ($card_editors as $editor): ?>
                    <option value="<?= htmlspecialchars($editor['id']) ?>" <?= ($editor['id'] === ($_POST['fk_card_id'] ?? $selected_content['fk_card_id'] ?? '')) ? 'selected' : '' ?>>
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
                <?= ButtonGenerator::render('action', 'save', 'Speichern', 'button-save') ?>
                <?= ButtonGenerator::render('action', 'delete', 'Löschen', 'button-delete') ?>
            </div>
        </form>
    </div>
</div>