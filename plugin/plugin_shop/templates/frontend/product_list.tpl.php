<h1>Produktliste</h1>
<ul>
<?php foreach ($products as $p): ?>
    <li>
        <a href="/shop/product?id=<?= htmlspecialchars($p['id']) ?>">
            <?= htmlspecialchars($p['name']) ?> - <?= number_format($p['price'], 2) ?> €
        </a>
    </li>
<?php endforeach; ?>
</ul>
