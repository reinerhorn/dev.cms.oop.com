<h1>Produktliste</h1>
<div class="product-list">
<?php foreach ($products as $p): ?>
    <div class="product-item">
        <a href="/shop/product?id=<?= htmlspecialchars($p['id']) ?>">
            <?= htmlspecialchars($p['name']) ?>
        </a> - <?= number_format($p['price'], 2) ?> €
        <a href="/shop/cart?action=add&id=<?= htmlspecialchars($p['id']) ?>">
            <button>In den Warenkorb</button>
        </a>
    </div>
<?php endforeach; ?>
</div>
