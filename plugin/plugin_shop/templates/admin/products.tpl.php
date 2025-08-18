<!-- products.tpl.php -->
<h1>Produkte verwalten</h1>

<?php if (!empty($products)): ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Preis</th>
                <th>Bestand</th>
                <th>Kategorie</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= htmlspecialchars($product['id']) ?></td>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td><?= number_format($product['price'], 2, ',', '.') ?> €</td>
                    <td><?= (int) $product['stock'] ?></td>
                    <td><?= htmlspecialchars($product['category_id'] ?? '-') ?></td>
                    <td>
                        <a href="/admin/shop/products/edit?id=<?= urlencode($product['id']) ?>">Bearbeiten</a> |
                        <a href="/admin/shop/products/delete?id=<?= urlencode($product['id']) ?>" onclick="return confirm('Wirklich löschen?')">Löschen</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Keine Produkte vorhanden.</p>
<?php endif; ?>

<p>
    <a href="/admin/shop/products/create">➕ Neues Produkt hinzufügen</a>
</p>