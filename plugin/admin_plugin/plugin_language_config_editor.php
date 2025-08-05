<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/helper/SelectGenerator.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/helper/ButtonGenerator.php';

$connection = getDbConnection();
$action = $_POST['action'] ?? '';
$form_name = $_POST['form_name'] ?? '';

$languages = $connection->query("SELECT * FROM trans_language ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$placeholders = $connection->query("SELECT * FROM translation_placeholder ORDER BY id")->fetch_all(MYSQLI_ASSOC);

if ($form_name === 'language_editor') {
    $languageId = $_POST['language_id'] ?? '';
    $languageLabel = $_POST['language_label'] ?? '';

    if ($action === 'store_language' && $languageId && $languageLabel) {
        $stmt = $connection->prepare("REPLACE INTO trans_language (id, label) VALUES (?, ?)");
        $stmt->bind_param("ss", $languageId, $languageLabel);
        $stmt->execute();
        echo "<div style='color:green;'>✅ Sprache gespeichert</div>";
    } elseif ($action === 'delete_language' && $languageId) {
        $stmt = $connection->prepare("DELETE FROM trans_language WHERE id = ?");
        $stmt->bind_param("s", $languageId);
        $stmt->execute();
        echo "<div style='color:red;'>🗑️ Sprache gelöscht</div>";
    }
}

if ($form_name === 'placeholder_editor') {
    $placeholderId = $_POST['placeholder_id'] ?? '';
    $selected = $_POST['selected_placeholder'] ?? '';

    if ($action === 'store_placeholder' && $placeholderId) {
        $stmt = $connection->prepare("REPLACE INTO translation_placeholder (id) VALUES (?)");
        $stmt->bind_param("s", $placeholderId);
        $stmt->execute();
        echo "<div style='color:green;'>✅ Platzhalter gespeichert</div>";
    } elseif ($action === 'delete_placeholder' && $selected) {
        $stmt = $connection->prepare("DELETE FROM translation_placeholder WHERE id = ?");
        $stmt->bind_param("s", $selected);
        $stmt->execute();
        echo "<div style='color:red;'>🗑️ Platzhalter gelöscht</div>";
    }
}

if ($form_name === 'translation_editor') {
    $fkPlaceholder = $_POST['fk_translation_placeholder'] ?? '';
    $fkLanguage = $_POST['fk_language_id'] ?? '';
    $label = $_POST['translation_label'] ?? '';

    $existingLabel = '';
    if ($fkPlaceholder && $fkLanguage) {
        $stmt = $connection->prepare("SELECT label FROM translation WHERE fk_translation_placeholder = ? AND fk_language_id = ?");
        $stmt->bind_param("ss", $fkPlaceholder, $fkLanguage);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $existingLabel = $row['label'];
        }
    }

    $translationLabel = $existingLabel;

    if ($action === 'store_translation' && $fkPlaceholder && $fkLanguage && $label) {
        $stmt = $connection->prepare("REPLACE INTO translation (fk_translation_placeholder, fk_language_id, label) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $fkPlaceholder, $fkLanguage, $label);
        $stmt->execute();
        echo "<div style='color:green;'>✅ Übersetzung gespeichert</div>";
    } elseif ($action === 'delete_translation' && $fkPlaceholder && $fkLanguage) {
        $stmt = $connection->prepare("DELETE FROM translation WHERE fk_translation_placeholder = ? AND fk_language_id = ?");
        $stmt->bind_param("ss", $fkPlaceholder, $fkLanguage);
        $stmt->execute();
        echo "<div style='color:red;'>🗑️ Übersetzung gelöscht</div>";
    }
}
?>

<div class="admin_container">
    <div class="admin_box">
        <h2>Sprachen verwalten</h2>
        <form method="post">
            <input type="hidden" name="form_name" value="language_editor">
            <label for="language_id">ID (z. B. de, en, fr)</label>
            <input type="text" name="language_id" class="input_color" value="<?= htmlspecialchars($_POST['language_id'] ?? '') ?>">

            <label for="language_label">Bezeichnung</label>
            <input type="text" name="language_label" class="input_color" value="<?= htmlspecialchars($_POST['language_label'] ?? '') ?>">

            <div class="buttons">
              <?= ButtonGenerator::render('action', 'save', 'Speichern', 'button-save') ?>
              <?= ButtonGenerator::render('action', 'delete', 'Löschen', 'button-delete') ?>
            </div>
        </form>
    </div>
</div>
<div class="admin_box">
    <h2>Platzhalter verwalten</h2>
    <form method="post">
        <input type="hidden" name="form_name" value="placeholder_editor">

        <?php
        $placeholderSelection = ($_POST['selected_placeholder'] ?? '') === 'neu' ? '' : ($_POST['selected_placeholder'] ?? '');
        echo SelectGenerator::render(
            'selected_placeholder',
            $placeholders,
            $placeholderSelection,
            'neu',
            'id',
            'id',
            ['onchange' => 'this.form.submit()']
        );
        ?>

        <label for="placeholder_id">Platzhalter-ID</label>
        <input type="text" name="placeholder_id" class="input_color" value="<?= htmlspecialchars(($placeholderSelection !== '') ? $placeholderSelection : '') ?>">

       <div class="buttons">
              <?= ButtonGenerator::render('action', 'save', 'Speichern', 'button-save', 'icon-save') ?>
              <?= ButtonGenerator::render('action', 'delete', 'Löschen', 'button-delete', 'icon-trash') ?>
            </div>
    </form>
</div>
<div class="admin_box">
    <h2>Übersetzungen verwalten</h2>
    <form method="post">
        <input type="hidden" name="form_name" value="translation_editor">

        <?php
        $selectedPlaceholder = $_POST['fk_translation_placeholder'] ?? '';
        $selectedLanguage = $_POST['fk_language_id'] ?? '';
        $translationLabel = $_POST['translation_label'] ?? '';

        $translationLabel = '';
        if ($selectedPlaceholder !== '' && $selectedLanguage !== '') {
            $stmt = $connection->prepare("SELECT label FROM translation WHERE fk_translation_placeholder = ? AND fk_language_id = ?");
            $stmt->bind_param("ss", $selectedPlaceholder, $selectedLanguage);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $translationLabel = $row['label'];
            }
        }

        echo SelectGenerator::render(
            'fk_translation_placeholder',
            $placeholders,
            $selectedPlaceholder,
            'neu',
            'id',
            'id',
            ['onchange' => 'this.form.submit()']
        );

        echo SelectGenerator::render(
            'fk_language_id',
            $languages,
            $selectedLanguage,
            'neu',
            'id',
            'label',
            ['onchange' => 'this.form.submit()']
        );
        ?>

        <label for="translation_label">Label</label>
        <input type="text" name="translation_label" class="input_color" value="<?= htmlspecialchars($translationLabel) ?>">

      <div class="buttons">
                
              <?= ButtonGenerator::render('action', 'save', 'Speichern', 'button-save', 'icon-save') ?>
              <?= ButtonGenerator::render('action', 'delete', 'Löschen', 'button-delete', 'icon-trash') ?>
            </div>
    </form>
</div>