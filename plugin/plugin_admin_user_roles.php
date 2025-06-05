<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.inc.php';
$db = getDbConnection();

// Nutzer und Rollen laden
$users = $db->query("SELECT id, username, email FROM login_users ORDER BY username")->fetch_all(MYSQLI_ASSOC);
$roles = $db->query("SELECT id, name FROM roles ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Verarbeiten des Formulars
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];

    // Alte Rollen löschen
    $db->query("DELETE FROM user_roles WHERE user_id = '$user_id'");

    // Neue Rollen zuweisen
    if (isset($_POST['roles']) && is_array($_POST['roles'])) {
        $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        foreach ($_POST['roles'] as $role_id) {
            $stmt->bind_param("ss", $user_id, $role_id);
            $stmt->execute();
        }
    }

    echo "<p>✅ Rollen für Benutzer gespeichert!</p>";
}

// Vorauswahl
$selected_user = $_POST['user_id'] ?? ($users[0]['id'] ?? '');
$assigned_roles = [];

if ($selected_user) {
    $res = $db->query("SELECT role_id FROM user_roles WHERE user_id = '$selected_user'");
    while ($row = $res->fetch_assoc()) {
        $assigned_roles[] = $row['role_id'];
    }
}
?>

<form method="post">
    <label>Benutzer auswählen:</label>
    <select name="user_id" onchange="this.form.submit()">
        <?php foreach ($users as $user): ?>
            <option value="<?= $user['id'] ?>" <?= $user['id'] === $selected_user ? 'selected' : '' ?>>
                <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['email']) ?>)
            </option>
        <?php endforeach; ?>
    </select>

    <fieldset>
        <legend>Rollen zuweisen:</legend>
        <?php foreach ($roles as $role): ?>
            <label>
                <input type="checkbox" name="roles[]" value="<?= $role['id'] ?>"
                    <?= in_array($role['id'], $assigned_roles) ? 'checked' : '' ?>>
                <?= htmlspecialchars($role['name']) ?>
            </label><br>
        <?php endforeach; ?>
    </fieldset>

    <button type="submit">Speichern</button>
</form>