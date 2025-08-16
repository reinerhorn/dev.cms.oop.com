<?php
class AdminShopController {
    private mysqli $db;

    public function __construct() {
        $this->db = CMSApp::getDb();
    }

    public function products() {
        $result = $this->db->query("SELECT * FROM products ORDER BY created_at DESC");
        $products = $result->fetch_all(MYSQLI_ASSOC);
        #require __DIR__ . '/../templates/admin/products.tpl.php';
        require_once $_SERVER['DOCUMENT_ROOT'] . '/plugin/plugin_shop/templates/admin/products.tpl.php';
    }

    public function categories() {
        #require __DIR__ . '/../templates/admin/categories.tpl.php';
        require_once $_SERVER['DOCUMENT_ROOT'] . '/plugin/plugin_shop/templates/admin/categories.tpl.php';
    }

    public function orders() {
        #require __DIR__ . '/../templates/admin/orders.tpl.php';
        require_once $_SERVER['DOCUMENT_ROOT'] . '/plugin/plugin_shop/templates/admin/orders.tpl.php';
    }

    public function payments() {
        #require __DIR__ . '/../templates/admin/payments.tpl.php';
        require_once $_SERVER['DOCUMENT_ROOT'] . '/plugin/plugin_shop/templates/admin/payments.tpl.php';
    }
    }

