<?php 
//product_detail.tpl.php

if ($product): ?>
    <div class="product-detail">
        <h1><?= htmlspecialchars($product['name']) ?></h1>

        <?php if (!empty($product['image_url'])): ?>
            <div class="product-image">
                <img src="<?= htmlspecialchars($product['image_url']) ?>"
                     alt="<?= htmlspecialchars($product['name']) ?>">
            </div>
        <?php endif; ?>

        <div class="product-description">
            <?= nl2br(htmlspecialchars($product['description'] ?? 'Keine Beschreibung verfügbar.')) ?>
        </div>

        <div class="product-price">
            <strong>Preis:</strong>
            <?= number_format((float)$product['price'], 2, ',', '.') ?> €
        </div>

        <form method="get" action="/shop/cart" class="add-to-cart-form">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="id" value="<?= htmlspecialchars($product['id']) ?>">

            <label for="qty">Menge:</label>
            <input type="number" name="qty" id="qty" value="1" min="1">

            <button type="submit">🛒 In den Warenkorb</button>
        </form>
    </div>
<?php else: ?>
    <p>⚠️ Produkt nicht gefunden.</p>
<?php endif; ?>