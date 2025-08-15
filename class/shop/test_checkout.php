<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
require_once __DIR__ . '/shop/CheckoutService.php';

// DB Verbindung
$db = CMSApp::getDb();

// Testdaten
$userId = 'user-admin-001'; // muss in login_users existieren
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

// Testausgabe
echo "=== Test: Checkout ===\n";

$checkout = new CheckoutService($db);

try {
    $result = $checkout->processCheckout(
        $userId,
        $cartItems,
        $billingAddress,
        $shippingAddress,
        $paymentMethod,
        $paymentAmount
    );

    echo "Bestellung erfolgreich abgeschlossen!\n";
    echo "Order-ID: {$result['order_id']}\n";
    echo "Payment-ID: {$result['payment_id']}\n";
    echo "Status: {$result['status']}\n";
} catch (Exception $e) {
    echo "Fehler beim Checkout: " . $e->getMessage() . "\n";
}

echo "=== Test abgeschlossen ===\n";
