<style> .admin-role-section {
    margin-bottom: 40px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    overflow-x: auto;
}

.admin-role-heading {
    font-size: 1.4rem;
    margin-bottom: 15px;
    color: #333;
}

.admin-role-table {
    width: 100%;
    min-width: 600px;
    border-collapse: collapse;
    font-size: 0.95rem;
}

.admin-role-table thead {
    background-color: #f5f5f5;
    display: table-header-group;
}

.admin-role-table th,
.admin-role-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

.admin-role-table th {
    font-weight: bold;
}

.role-select {
    padding: 5px 8px;
    font-size: 0.9rem;
}

.save-button {
    padding: 5px 10px;
    background-color: #0078D4;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.save-button:hover {
    background-color: #005fa3;
}

.success-message {
    background-color: #e6ffed;
    color: #2e7d32;
    padding: 10px;
    margin-bottom: 20px;
    border: 1px solid #c3e6cb;
    border-radius: 5px;
}

.admin-role-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: space-between;
}

.admin-role-section {
    flex: 0 1 calc(50% - 10px);
    min-width: 300px;
}

@media (max-width: 768px) {
    .admin-role-section {
        flex: 1 1 100%;
    }
}

.admin-role-table td:last-child {
    white-space: nowrap;
}
</style>

</style>
<?php
// ➤ WICHTIG: CSS-Klassen werden in /css/admin.css definiert (z. B. .admin-role-table, .success-message)

include_once $_SERVER['DOCUMENT_ROOT'] . "/CMSApp.php"; 
$db = CMSApp::getDb();

// Verarbeitung des Formulars
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role'], $_POST['page_id'])) {
    foreach ($_POST['page_id'] as $pageId) {
        $role = $_POST['role'][$pageId] ?? null;
        $stmt = $db->prepare("UPDATE page SET role = ? WHERE id = ?");
        $stmt->bind_param("is", $role, $pageId);
        $stmt->execute();
    }
    $_SESSION['last_saved_roles'] = $_POST['role'];
    echo "<div class='success-message'>✅ Rollen gespeichert.</div>";
}

// Header-Rollen speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['header_role'], $_POST['header_id'])) {
    foreach ($_POST['header_id'] as $headerId) {
        $role = $_POST['header_role'][$headerId] ?? null;
        $stmt = $db->prepare("UPDATE header SET role = ? WHERE id = ?");
        $stmt->bind_param("is", $role, $headerId);
        $stmt->execute();
    }
    echo "<div class='success-message'>✅ Header-Rollen gespeichert.</div>";
}

// Footer-Rollen speichern (nur wenn die Spalte 'role' existiert)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['footer_role'], $_POST['footer_id'])) {
    foreach ($_POST['footer_id'] as $footerId) {
        $role = $_POST['footer_role'][$footerId] ?? null;
        $stmt = $db->prepare("UPDATE footer SET role = ? WHERE id = ?");
        $stmt->bind_param("is", $role, $footerId);
        $stmt->execute();
    }
    echo "<div class='success-message'>✅ Footer-Rollen gespeichert.</div>";
}

$result = $db->query("SELECT UNIX_TIMESTAMP(id) as id, fk_translation_placeholder as name, role FROM page ORDER BY name ASC");

echo "<div class='admin-role-container'>";
echo "<section class='admin-role-section'>";
echo "<h2 class='admin-role-heading'>Rollen-Zuweisung für Seiten</h2>";
echo "<table class='admin-role-table'>";
echo "<thead><tr><th>Seiten-ID</th><th>Name</th><th>Rolle</th><th>Aktion</th></tr></thead>";
echo "<tbody>";
while ($row = $result->fetch_assoc()) {
    $pageId = htmlspecialchars($row['id']);
    $name = htmlspecialchars($row['name']);
    $role = isset($_SESSION['last_saved_roles'][$pageId]) ? (int)$_SESSION['last_saved_roles'][$pageId] : (int)$row['role'];

    echo "<tr><form method='post'>";
    echo "<td>$pageId</td>";
    echo "<td>$name</td>";
    echo "<td>
        <select name='role[$pageId]' class='role-select'>
            <option value='' " . ($role === 0 ? 'selected' : '') . ">Öffentlich</option>
            <option value='1' " . ($role === 1 ? 'selected' : '') . ">Admin</option>
            <option value='2' " . ($role === 2 ? 'selected' : '') . ">Member</option>
        </select>
    </td>";
    echo "<td>
        <input type='hidden' name='page_id[]' value='$pageId'>
        <button type='submit' class='save-button'>Speichern</button>
    </td>";
    echo "</form></tr>";
}
echo "</tbody></table></section>";

unset($_SESSION['last_saved_roles']);

// HEADER
$resultHeader = $db->query("SELECT id, label, role FROM header ORDER BY label ASC");

echo "<section class='admin-role-section'>";
echo "<h2 class='admin-role-heading'>Rollen-Zuweisung für Header</h2>";
echo "<table class='admin-role-table'>";
echo "<thead><tr><th>Header-ID</th><th>Label</th><th>Rolle</th><th>Aktion</th></tr></thead>";
echo "<tbody>";
while ($row = $resultHeader->fetch_assoc()) {
    $id = htmlspecialchars($row['id']);
    $label = htmlspecialchars($row['label']);
    $role = (int)$row['role'];

    echo "<tr><form method='post'>";
    echo "<td>$id</td>";
    echo "<td>$label</td>";
    echo "<td>
        <select name='header_role[$id]' class='role-select'>
            <option value='' " . ($role === 0 ? 'selected' : '') . ">Öffentlich</option>
            <option value='1' " . ($role === 1 ? 'selected' : '') . ">Admin</option>
            <option value='2' " . ($role === 2 ? 'selected' : '') . ">Member</option>
        </select>
    </td>";
    echo "<td>
        <input type='hidden' name='header_id[]' value='$id'>
        <button type='submit' class='save-button'>Speichern</button>
    </td>";
    echo "</form></tr>";
}
echo "</tbody></table></section>";

// FOOTER
$resultFooter = $db->query("SELECT id, headline, role FROM footer ORDER BY headline ASC");

echo "<section class='admin-role-section'>";
echo "<h2 class='admin-role-heading'>Rollen-Zuweisung für Footer</h2>";
echo "<table class='admin-role-table'>";
echo "<thead><tr><th>Footer-ID</th><th>Headline</th><th>Rolle</th><th>Aktion</th></tr></thead>";
echo "<tbody>";
while ($row = $resultFooter->fetch_assoc()) {
    $id = htmlspecialchars($row['id']);
    $headline = htmlspecialchars($row['headline']);
    $role = (int)$row['role'];

    echo "<tr><form method='post'>";
    echo "<td>$id</td>";
    echo "<td>$headline</td>";
    echo "<td>
        <select name='footer_role[$id]' class='role-select'>
            <option value='' " . ($role === 0 ? 'selected' : '') . ">Öffentlich</option>
            <option value='1' " . ($role === 1 ? 'selected' : '') . ">Admin</option>
            <option value='2' " . ($role === 2 ? 'selected' : '') . ">Member</option>
        </select>
    </td>";
    echo "<td>
        <input type='hidden' name='footer_id[]' value='$id'>
        <button type='submit' class='save-button'>Speichern</button>
    </td>";
    echo "</form></tr>";
}
echo "</tbody></table></section>";
echo "</div>";
?>