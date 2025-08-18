<!-- orders.tpl.php
     plugin/plugin_shop/templates/admin/orders.tpl.php -->
<h1>Bestellungen</h1>

<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th>Order-ID</th>
            <th>User-ID</th>
            <th>Gesamtbetrag</th>
            <th>Status</th>
            <th>Datum</th>
            <th>Aktionen</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($orders)): ?>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= htmlspecialchars($order['id']) ?></td>
                    <td><?= htmlspecialchars($order['user_id']) ?></td>
                    <td><?= number_format($order['total'], 2, ',', '.') ?> €</td>
                    <td><?= htmlspecialchars($order['status']) ?></td>
                    <td><?= htmlspecialchars($order['created_at']) ?></td>
                    <td>
                        <a href="/admin/shop/orders/view?id=<?= urlencode($order['id']) ?>">Details</a>
                        <a href="/admin/shop/orders/delete?id=<?= urlencode($order['id']) ?>" onclick="return confirm('Bestellung wirklich löschen?')">Löschen</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6">Keine Bestellungen vorhanden.</td></tr>
        <?php endif; ?>
    </tbody>
</table>