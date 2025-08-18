<?php
// plugin_shop.php  
 require_once $_SERVER['DOCUMENT_ROOT'] . '/plugin/plugin_shop/controllers/FrontendShopController.php';
 require_once $_SERVER['DOCUMENT_ROOT'] . '/plugin/plugin_shop/controllers/AdminShopController.php';
error_log("🛒 plugin_shop wurde geladen");
error_log("📦 listProducts() gestartet");

// Fallback-Ausgabe, wenn Plugin direkt im Browser aufgerufen wird
if (php_sapi_name() !== 'cli' && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    echo "<h1>🛒 Shop Plugin</h1><p>Dieses Plugin wird über das CMS geladen. Rufe <a href='/shop'>/shop</a> auf.</p>";
    exit;
}

// Plugin-Info
return [
    'name' => 'Shop',
    'slug' => 'plugin_shop', 
    'routes' => [
        // Frontend
        '/plugin_shop' => [FrontendShopController::class, 'listProducts'],
        '/plugin_shop/product' => [FrontendShopController::class, 'detail'],
        '/plugin_shop/cart' => [FrontendShopController::class, 'cart'],
        '/plugin_shop/checkout' => [FrontendShopController::class, 'checkout'],

        // Admin
        '/admin/shop/products' => [AdminShopController::class, 'products'],
        '/admin/shop/categories' => [AdminShopController::class, 'categories'],
        '/admin/shop/orders' => [AdminShopController::class, 'orders'],
        '/admin/shop/payments' => [AdminShopController::class, 'payments'],
    ]
];
