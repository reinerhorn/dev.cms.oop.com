<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
require_once __DIR__ . '/CheckoutService.php';

// DB Verbindung
$db = CMSApp::getDb();

$stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE user_id = ?");
$stmt->bind_param("s", $userId);
$userId = 'user-admin-001'; // muss in login_users existieren
$stmt->execute();
$countOrdersBefore = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

echo "Vor Checkout: {$countOrdersBefore} Bestellungen für User {$userId}\n";

// Testdaten
$cartItems = [
    [
        'product_id' => 'aaaaaaa1-aaaaaaaaaaa1', // Laptop Pro 15"
        'quantity' => 1,
        'price' => 1499.99
    ],
    [
        'product_id' => 'aaaaaaa3-aaaaaaaaaaa3', // Bluetooth Kopfhörer
        'quantity' => 1,
        'price' => 199.99
    ]
];
// Bestand vor Checkout sicherstellen
require_once __DIR__ . '/StockManager.php';
$stockManager = new StockManager($db);

foreach ($cartItems as $item) {
    $currentStock = $stockManager->getStock($item['product_id']);
    if ($currentStock < $item['quantity']) {
        $needed = $item['quantity'] - $currentStock;
        $stockManager->increaseStock($item['product_id'], $needed, 'adjustment');
        $newStock = $stockManager->getStock($item['product_id']);
        echo "Bestand für Produkt {$item['product_id']} wurde um {$needed} erhöht (alt: {$currentStock}, neu: {$newStock})\n";
    } else {
        echo "Bestand für Produkt {$item['product_id']} ist ausreichend (aktuell: {$currentStock}), keine Änderung nötig.\n";
    }
}

$productIds = array_map(function($item) use ($db) {
    return "'" . $db->real_escape_string($item['product_id']) . "'";
}, $cartItems);
$productIdsList = implode(",", $productIds);

// Filter: nur Bewegungen der letzten 5 Minuten
$timeLimit = date('Y-m-d H:i:s', strtotime('-5 minutes'));
// Prepared Statement Version
$productIds = array_column($cartItems, 'product_id');
$placeholders = implode(',', array_fill(0, count($productIds), '?'));

$query = "
    SELECT * 
    FROM stock_movements 
    WHERE product_id IN ($placeholders) 
      AND created_at >= ?
    ORDER BY created_at DESC
    LIMIT 5
";

$stmt = $db->prepare($query);
$types = str_repeat('s', count($productIds)) . 's';
$params = array_merge($productIds, [$timeLimit]);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$movementsRes = $stmt->get_result();
$stmt->close();

echo "--- Stock Movements (letzte 5 Minuten, max. 5 Einträge) ---\n";
if ($movementsRes->num_rows === 0) {
    echo "Keine aktuellen Lagerbewegungen gefunden.\n";
} else {
    while ($mov = $movementsRes->fetch_assoc()) {
        echo "Produkt-ID: {$mov['product_id']} | Änderung: {$mov['change_qty']} | Grund: {$mov['reason']} | Datum: {$mov['created_at']}\n";
    }
}

$billingAddress = [
    'street' => 'Musterstraße 12',
    'city' => 'Berlin',
    'postal_code' => '10115',
    'country' => 'Deutschland'
];
$shippingAddress = [
    'street' => 'Lagerweg 5',
    'city' => 'Hamburg',
    'postal_code' => '20095',
    'country' => 'Deutschland'
];
$paymentMethod = 'credit_card';
$paymentAmount = 1699.98;

echo "=== Test: Checkout ===\n";

$checkout = new CheckoutService($db);

try {
    $result = $checkout->processCheckout(
        $userId,
        $cartItems,
        $billingAddress,
        $shippingAddress,
        (float)$paymentAmount,
        $paymentMethod
    );

    // Aktuelle Zeit als Referenz für neue Lagerbewegungen nach Checkout
    $preCheckoutTime = date('Y-m-d H:i:s');

    echo "Bestellung erfolgreich abgeschlossen!\n";
    echo "Order-ID: {$result['order_id']}\n";
    echo "Payment-ID: {$result['payment_id']}\n";
    echo "Status: {$result['status']}\n";

    // Bestellung auslesen
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->bind_param("s", $result['order_id']);
    $stmt->execute();
    $orderData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo "Bestelldaten: " . print_r($orderData, true) . "\n";

    // Order-Items
    $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->bind_param("s", $result['order_id']);
    $stmt->execute();
    $itemsRes = $stmt->get_result();
    $stmt->close();
    echo "--- Produkte ---\n";
    while ($row = $itemsRes->fetch_assoc()) {
        echo "- Produkt-ID: {$row['product_id']} | Menge: {$row['quantity']} | Preis: {$row['price']}\n";
    }

    // Zahlungen
    $stmt = $db->prepare("SELECT * FROM payments WHERE order_id = ?");
    $stmt->bind_param("s", $result['order_id']);
    $stmt->execute();
    $payRes = $stmt->get_result();
    $stmt->close();
    echo "--- Zahlungen ---\n";
    while ($pay = $payRes->fetch_assoc()) {
        echo "- Payment-ID: {$pay['id']} | Betrag: {$pay['amount']} | Status: {$pay['status']} | Methode: {$pay['method']}\n";
    }

    // Lagerbewegungen nach Checkout abfragen
    $postCheckoutTime = date('Y-m-d H:i:s');

    $queryPost = "
        SELECT sm.* 
        FROM stock_movements sm
        JOIN order_items oi 
            ON sm.product_id = oi.product_id
           AND oi.order_id = '{$db->real_escape_string($result['order_id'])}'
        WHERE sm.reason = 'sale'
        ORDER BY sm.created_at DESC
    ";
    $movementsPostRes = $db->query($queryPost);

    echo "--- Lagerbewegungen dieser Bestellung ---\n";
    if ($movementsPostRes->num_rows === 0) {
        echo "Keine neuen Lagerbewegungen nach dem Checkout gefunden.\n";
    } else {
        while ($movPost = $movementsPostRes->fetch_assoc()) {
            echo "Produkt-ID: {$movPost['product_id']} | Änderung: {$movPost['change_qty']} | Grund: {$movPost['reason']} | Datum: {$movPost['created_at']}\n";
        }
    }

} catch (Exception $e) {
    echo "Fehler beim Checkout: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "=== Test abgeschlossen ===\n";

echo "---\n";

// Zusammenfassung: Anzahl aller Bestellungen und Summe aller Bestellbeträge
$stmt = $db->prepare("SELECT COUNT(*) AS total_orders, SUM(total) AS total_amount FROM orders WHERE user_id = ?");
$stmt->bind_param("s", $userId);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "--- Bestellübersicht ---\n";
echo "Anzahl Bestellungen: {$summary['total_orders']} | Gesamtumsatz: " . number_format((float)$summary['total_amount'], 2) . " EUR\n";
echo "---\n";
