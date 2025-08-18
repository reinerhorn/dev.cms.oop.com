<?php
/**
 * Warenkorb Template
 * Erwartet: $cartItems = [
 *   ['id' => '...', 'name' => 'Produktname', 'price' => 10.00, 'quantity' => 2]
 * ];
 */
?>
<div class="cart">
    <h2>🛒 Dein Warenkorb</h2>

    <?php if (!empty($cartItems)): ?>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Produkt</th>
                    <th>Menge</th>
                    <th>Preis</th>
                    <th>Gesamt</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total = 0;
                foreach ($cartItems as $item): 
                    $lineTotal = $item['price'] * $item['quantity'];
                    $total += $lineTotal;
                ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= (int)$item['quantity'] ?></td>
                        <td><?= number_format($item['price'], 2, ',', '.') ?> €</td>
                        <td><?= number_format($lineTotal, 2, ',', '.') ?> €</td>
                        <td>
                            <a href="/shop/cart?action=add&id=<?= urlencode($item['id']) ?>" title="Menge erhöhen">➕</a>
                            <a href="/shop/cart?action=remove&id=<?= urlencode($item['id']) ?>" title="Menge verringern">➖</a>
                            <a href="/shop/cart?action=delete&id=<?= urlencode($item['id']) ?>" title="Produkt entfernen">❌</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:right"><strong>Gesamt:</strong></td>
                    <td><strong><?= number_format($total, 2, ',', '.') ?> €</strong></td>
                </tr>
            </tfoot>
        </table>

        <div class="cart-actions">
            <a href="/shop/checkout" class="btn btn-primary">Zur Kasse</a>
            <a href="/shop" class="btn btn-secondary">Weiter einkaufen</a>
        </div>
    <?php else: ?>
        <p>Dein Warenkorb ist leer.</p>
        <a href="/shop" class="btn btn-secondary">Produkte ansehen</a>
    <?php endif; ?>
</div>