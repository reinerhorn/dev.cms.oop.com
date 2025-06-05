<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.inc.php';
$db = getDbConnection();

// Rollen laden
$roles = $db->query("SELECT * FROM roles ORDER BY name")->fetch_all(MYSQLI_ASSOC);
// Berechtigungen laden
$permissions = $db->query("SELECT * FROM permissions ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Zuweisungen speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role_id'])) {
    $role_id = $_POST['role_id'];
    $db->query("DELETE FROM role_permissions WHERE role_id = '$role_id'");

    if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
        $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($_POST['permissions'] as $perm_id) {
            $stmt->bind_param("ss", $role_id, $perm_id);
            $stmt->execute();
        }
    }
    echo "<p>✅ Rechte aktualisiert!</p>";
}

// Gewählte Rolle und aktuelle Rechte
$selected_role = $_POST['role_id'] ?? ($roles[0]['id'] ?? '');
$assigned = [];
if ($selected_role) {
    $res = $db->query("SELECT permission_id FROM role_permissions WHERE role_id = '$selected_role'");
    while ($row = $res->fetch_assoc()) {
        $assigned[] = $row['permission_id'];
    }
}
?>

<form method="post">
    <label>Rolle wählen:</label>
    <select name="role_id" onchange="this.form.submit()">
        <?php foreach ($roles as $role): ?>
            <option value="<?= $role['id'] ?>" <?= $role['id'] === $selected_role ? 'selected' : '' ?>>
                <?= htmlspecialchars($role['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <fieldset>
        <legend>Berechtigungen:</legend>
        <?php foreach ($permissions as $perm): ?>
            <label>
                <input type="checkbox" name="permissions[]" value="<?= $perm['id'] ?>"
                    <?= in_array($perm['id'], $assigned) ? 'checked' : '' ?>>
                <?= htmlspecialchars($perm['name']) ?>
            </label><br>
        <?php endforeach; ?>
    </fieldset>

    <button type="submit">Rechte speichern</button>
</form>
