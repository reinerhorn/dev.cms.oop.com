<?php
// form_editor_plaintext.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['record_selection']) || isset($_POST['id'])) && !isset($_POST['action'])) {
    $_POST['action'] = 'edit';
}

if (isset($_POST['record_selection_plaintext']) && (!isset($_POST['form_name']) || $_POST['form_name'] === 'editor_plaintext')) {
    $_POST['id'] = $_POST['record_selection_plaintext'];
    $_POST['form_name'] = 'editor_plaintext';
    $_POST['action'] = 'edit';
}

$plaintextOptions = [];
$stmt = $connection->prepare("SELECT * FROM p_content_plaintext");
$stmt->execute();
$result = $stmt->get_result();
while ($rec = $result->fetch_assoc()) {
    $plaintextOptions[] = $rec;
}
array_unshift($plaintextOptions, ['id' => 'neu', 'label' => 'neu']);

$selectedId = $_POST['record_selection_plaintext'] ?? $id ?? '';
$headline = $image_path = $link = $image_description = $label = $text = $idx = '';

if ($selectedId !== '') {
    $stmt = $connection->prepare("SELECT * FROM p_content_plaintext WHERE id = ?");
    $stmt->bind_param("s", $selectedId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($record = $result->fetch_assoc()) {
        extract($record);
    }
}
?>
<div class="admin_box">
    <h2>Plaintext Editor</h2>
    <form name="editor_plaintext" action="" method="post">
        <input type="hidden" name="form_name" value="editor_plaintext">
        <input type="hidden" name="action" value="<?= htmlspecialchars($action ?? 'store') ?>">
        <input type="hidden" name="id" id="plaintext_id" value="<?= htmlspecialchars($selectedId) ?>">

        <?= SelectGenerator::render(
            'record_selection_plaintext',
            $plaintextOptions,
            $selectedId,
            'auswählen...',
            'id',
            'label',
            ['onchange' => "document.getElementById('plaintext_id').value=this.value; this.form.querySelector('[name=action]').value='edit'; this.form.submit();"]
        ) ?>

        <label for="headline">Title</label>
        <input type="text" id="headline" name="headline" class="input_color" value="<?= htmlspecialchars($headline) ?>" required>

        <label for="image_path">Image Path</label>
        <input type="text" name="image_path" class="input_color" value="<?= htmlspecialchars($image_path) ?>">

        <label for="link">Link</label>
        <input type="text" name="link" class="input_color" value="<?= htmlspecialchars($link) ?>">

        <label for="image_description">Image Description</label>
        <input type="text" name="image_description" class="input_color" value="<?= htmlspecialchars($image_description) ?>">

        <label for="idx">Index</label>
        <input type="text" name="idx" class="input_color" value="<?= htmlspecialchars($idx) ?>">

        <label for="text">Text</label>
        <textarea name="text" id="text" class="input_color"><?= htmlspecialchars($text) ?></textarea>

        <div class="buttons">
            <button type="button" class="button-save" onclick="setActionAndSubmit(this.form, 'store')">Speichern</button>
            <button type="button" class="button-delete" onclick="setActionAndSubmit(this.form, 'delete')">Löschen</button>
        </div>
    </form>
</div>
