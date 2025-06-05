<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
$connection = getDbConnection();

$form_label = $_POST['form_label'] ?? '';
$target_table = '';
$is_login_or_register = in_array(strtolower($form_label), ['login', 'register']);

// Ziel-Tabelle setzen
if ($is_login_or_register) {
    $target_table = 'plugin_login_users';
} else {
    $target_table = 'generated_form_' . strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $form_label));
}

// Formularfelder vorbereiten
$columns = [];
$placeholders = [];
$values = [];
$types = '';

foreach ($_POST as $key => $value) {
    if (in_array($key, ['form_label', 'submit'])) continue;

    $columns[] = $key;
    $placeholders[] = '?';
    $values[] = $value;

    // Datentyp raten
    if (is_numeric($value)) {
        $types .= 'i';
    } else {
        $types .= 's';
    }
}

// Spezialfall: Registrierung — Passwort hashen
if ($is_login_or_register && in_array('password', $columns)) {
    $password_index = array_search('password', $columns);
    $values[$password_index] = password_hash($values[$password_index], PASSWORD_BCRYPT);
}

// SQL aufbauen
$sql = "INSERT INTO `$target_table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
$stmt = $connection->prepare($sql);
$stmt->bind_param($types, ...$values);

// Ausführen
if ($stmt->execute()) {
    echo "<div style='color:green;'>Formular erfolgreich gespeichert.</div>";
} else {
    echo "<div style='color:red;'>Fehler beim Speichern: " . $stmt->error . "</div>";
}
$stmt->close();
?>
