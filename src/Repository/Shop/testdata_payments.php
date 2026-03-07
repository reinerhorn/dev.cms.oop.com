<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/class/shop/PaymentManager.php";
#require_once __DIR__ . '/../../config/config.php';
#require_once __DIR__ . '/class/shop/PaymentManager.php';

$db = CMSApp::getDb();
$paymentManager = new PaymentManager($db);

$echoTest = "=== Test: Payments ===\n";
echo $echoTest;
// Erste vorhandene Order-ID automatisch aus DB holen
$result = $db->query("SELECT id FROM orders LIMIT 1");
if (!$result || $result->num_rows === 0) {
    die("Fehler: Keine Bestellung in der orders-Tabelle gefunden.\n");
}
$orderId = $result->fetch_assoc()['id'];

// Beispiel-Daten
$amount = 1699.98;
$method = 'credit_card';

// 1. Neue Zahlung anlegen
try {
    $paymentId = $paymentManager->createPayment($orderId, $amount, $method, 'pending');
    echo "Zahlung angelegt: $paymentId\n";
} catch (Exception $e) {
    die("Fehler: " . $e->getMessage() . "\n");
}

// 2. Zahlung per ID abrufen
$payment = $paymentManager->getPaymentById($paymentId);
if ($payment) {
    echo "Zahlung gefunden:\n";
    print_r($payment);
} else {
    echo "Keine Zahlung gefunden!\n";
}

// 3. Zahlungsstatus aktualisieren
if ($paymentManager->updatePaymentStatus($paymentId, 'completed')) {
    echo "Zahlungsstatus auf 'completed' gesetzt.\n";
} else {
    echo "Fehler beim Aktualisieren des Zahlungsstatus.\n";
}

// 4. Zahlungen zu einer Bestellung abrufen
$payments = $paymentManager->getPaymentsByOrderId($orderId);
echo "--- Zahlungen zu Order $orderId ---\n";
if (!empty($payments)) {
    foreach ($payments as $p) {
        echo "Payment-ID: {$p['id']} | Status: {$p['status']} | Betrag: {$p['amount']} | Datum: {$p['transaction_date']}\n";
    }
} else {
    echo "Keine Zahlungen für diese Bestellung gefunden.\n";
}

echo "=== Test abgeschlossen ===\n";
