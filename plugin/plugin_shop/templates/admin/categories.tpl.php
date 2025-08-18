categories.tpl.php
<?php
// Erwartet: $categories = Array mit allen Kategorien (id, name, description, created_at)

echo "<h1>Kategorien verwalten</h1>";

// Button: Neue Kategorie
echo ButtonGenerator::render(
    "add_category",
    "add",
    "➕ Neue Kategorie",
    "button-primary"
);

if (!empty($categories)): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Beschreibung</th>
                <th>Erstellt am</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $cat): ?>
            <tr>
                <td><?= htmlspecialchars($cat['id']) ?></td>
                <td><?= htmlspecialchars($cat['name']) ?></td>
                <td><?= htmlspecialchars($cat['description']) ?></td>
                <td><?= htmlspecialchars($cat['created_at']) ?></td>
                <td>
                    <?= ButtonGenerator::render("edit_category", $cat['id'], "✏️ Bearbeiten", "button-save") ?>
                    <?= ButtonGenerator::render("delete_category", $cat['id'], "🗑️ Löschen", "button-delete") ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Keine Kategorien vorhanden.</p>
<?php endif; ?>