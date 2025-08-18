<?php
class FrontendShopController {
    private mysqli $db;

    public function __construct() {
        $this->db = CMSApp::getDb();
    }

    public function listProducts() {
        $result = $this->db->query("SELECT * FROM products ORDER BY name ASC");
        $products = $result->fetch_all(MYSQLI_ASSOC);
        require_once $_SERVER['DOCUMENT_ROOT'] . '/plugin/plugin_shop/templates/frontend/product_list.tpl.php';         
    }

    public function detail() {
        $id = $_GET['id'] ?? '';
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        require_once $_SERVER['DOCUMENT_ROOT'] . '/plugin/plugin_shop/templates/frontend/product_detail.tpl.php';
       
    }

    public function cart() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $action = $_GET['action'] ?? null;
        $productId = $_GET['id'] ?? null;

        if ($action && $productId) {
            switch ($action) {
                case 'add':
                    $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + 1;
                    break;
                case 'remove':
                    if (!empty($_SESSION['cart'][$productId])) {
                        $_SESSION['cart'][$productId]--;
                        if ($_SESSION['cart'][$productId] <= 0) {
                            unset($_SESSION['cart'][$productId]);
                        }
                    }
                    break;
                case 'delete':
                    unset($_SESSION['cart'][$productId]);
                    break;
            }
        }

        require_once $_SERVER['DOCUMENT_ROOT'] . '/plugin/plugin_shop/templates/frontend/cart.tpl.php'; 
    }

    public function checkout() {
         require_once $_SERVER['DOCUMENT_ROOT'] . '/plugin/plugin_shop/templates/frontend/checkout.tpl.php';

         
    }
}
