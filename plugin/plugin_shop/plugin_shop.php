<?php
// plugin_shop.php  
require_once __DIR__ . '/controllers/FrontendShopController.php';
require_once __DIR__ . '/controllers/AdminShopController.php';
// Plugin-Info
return [
    'name' => 'Shop',
    'slug' => 'plugin_shop',
    'routes' => [
        // Frontend
        '/shop' => [FrontendShopController::class, 'listProducts'],
        '/shop/product' => [FrontendShopController::class, 'detail'],
        '/shop/cart' => [FrontendShopController::class, 'cart'],
        '/shop/checkout' => [FrontendShopController::class, 'checkout'],

        // Admin
        '/admin/shop/products' => [AdminShopController::class, 'products'],
        '/admin/shop/categories' => [AdminShopController::class, 'categories'],
        '/admin/shop/orders' => [AdminShopController::class, 'orders'],
        '/admin/shop/payments' => [AdminShopController::class, 'payments'],
    ]
];
