<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/class/shop/CustomerManager.php";
#require_once __DIR__ . '/../../config/config.inc.php'; // Pfad zur zentralen Config
#require_once __DIR__ . '/../../shop/CustomerManager.php';

// DB-Verbindung holen
$mysqli = getDbConnection();

// CustomerManager-Instanz erstellen
$customerManager = new CustomerManager($mysqli);

// Test-User-ID (muss in login_users existieren)
$userId = 'user-admin-001';

try {
    echo "=== Test: Customer Addresses ===\n";

    // Alte Testadressen des Users entfernen
    $mysqli->query("DELETE FROM customer_addresses WHERE user_id = '{$userId}'");
    echo "Alte Adressen für User {$userId} wurden gelöscht.\n";

    // 1. Billing-Adresse hinzufügen
    $billingId = $customerManager->addAddress(
        $userId,
        'billing',
        'Musterstraße 12',
        'Berlin',
        '10115',
        'Deutschland'
    );
    echo "Billing-Adresse angelegt: $billingId\n";

    // 2. Shipping-Adresse hinzufügen
    $shippingId = $customerManager->addAddress(
        $userId,
        'shipping',
        'Lagerweg 5',
        'Hamburg',
        '20095',
        'Deutschland'
    );
    echo "Shipping-Adresse angelegt: $shippingId\n";

    // 3. Adressen des Users abrufen
    $addresses = $customerManager->getAddressesByUser($userId);
    echo "\n--- Adressen für User: $userId ---\n";
    if (empty($addresses)) {
        echo "Keine Adressen gefunden.\n";
    } else {
        foreach ($addresses as $address) {
            echo strtoupper($address['type']) . ":\n";
            echo "  Straße: {$address['street']}\n";
            echo "  Stadt: {$address['city']}\n";
            echo "  PLZ: {$address['postal_code']}\n";
            echo "  Land: {$address['country']}\n\n";
        }
    }

    echo "\n=== Test abgeschlossen ===\n";

} catch (Exception $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}