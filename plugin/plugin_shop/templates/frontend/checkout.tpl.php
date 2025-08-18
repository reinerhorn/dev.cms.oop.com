<div class="checkout-container">
    <h1>Checkout</h1>

    <?php if (!empty($_SESSION['cart'])): ?>
        <h2>Dein Warenkorb</h2>
        <table class="checkout-cart">
            <thead>
                <tr>
                    <th>Produkt</th>
                    <th>Menge</th>
                    <th>Preis</th>
                    <th>Gesamt</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total = 0;
                foreach ($_SESSION['cart'] as $item): 
                    $subtotal = $item['price'] * $item['quantity'];
                    $total += $subtotal;
                ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= (int)$item['quantity'] ?></td>
                        <td><?= number_format($item['price'], 2, ',', '.') ?> €</td>
                        <td><?= number_format($subtotal, 2, ',', '.') ?> €</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p><strong>Gesamtsumme: <?= number_format($total, 2, ',', '.') ?> €</strong></p>

        <h2>Kundendaten</h2>
        <form action="/shop/checkout" method="post">
            <label for="name">Vollständiger Name:</label><br>
            <input type="text" id="name" name="name" required><br><br>

            <label for="address">Adresse:</label><br>
            <textarea id="address" name="address" required></textarea><br><br>

            <label for="payment">Zahlungsmethode:</label><br>
            <select id="payment" name="payment" required>
                <option value="paypal">PayPal</option>
                <option value="creditcard">Kreditkarte</option>
                <option value="bank">Überweisung</option>
            </select><br><br>

           <?php 
               require_once $_SERVER['DOCUMENT_ROOT'] . '/class/helper/ButtonGenerator.php';
               echo ButtonGenerator::render("place_order", "submit", "Bestellung abschließen", "btn-primary");
           ?>
        </form>
    <?php else: ?>
        <p>Dein Warenkorb ist leer.</p>
    <?php endif; ?>
</div>
