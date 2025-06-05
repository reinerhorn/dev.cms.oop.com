<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
include_once $_SERVER['DOCUMENT_ROOT'] . "/dev/formular_generator_function.php";

$connection = getDbConnection();
$formulare = $connection->query("SELECT * FROM p_content_formular ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

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
        <button type="submit" name="generate_html">HTML generieren &amp; speichern</button>
    </form>
</div>
