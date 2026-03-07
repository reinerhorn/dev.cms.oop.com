<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/class/shop/CustomerManager.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/class/shop/PaymentManager.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/class/shop/StockManager.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/class/shop/CustomerAddressManager.php";

#require_once '/class/shop/CustomerManager.php';
#require_once 'CustomerAddressManager.php';
#require_once 'PaymentManager.php';
#require_once 'StockManager.php';

class CheckoutService
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function createOrder(string $userId, array $cartItems): string
    {
        $orderId = $this->generateUuid();
        $total = 0.0;

        foreach ($cartItems as $item) {
            $total += $item['quantity'] * $item['price'];
        }

        $status = 'pending';
        $createdAt = date('Y-m-d H:i:s');

        // Insert into orders
        $stmtOrder = $this->db->prepare("INSERT INTO orders (id, user_id, total, status, created_at) VALUES (?, ?, ?, ?, ?)");
        if (!$stmtOrder) {
            throw new Exception("Prepare statement failed: " . $this->db->error);
        }
        $stmtOrder->bind_param("ssdss", $orderId, $userId, $total, $status, $createdAt);
        if (!$stmtOrder->execute()) {
            throw new Exception("Execute statement failed: " . $stmtOrder->error);
        }
        $stmtOrder->close();

        // Insert into order_items
        $stmtItem = $this->db->prepare("INSERT INTO order_items (id, order_id, product_id, quantity, price) VALUES (?, ?, ?, ?, ?)");
        if (!$stmtItem) {
            throw new Exception("Prepare statement failed: " . $this->db->error);
        }
        foreach ($cartItems as $item) {
            $itemId = $this->generateUuid();
            $productId = $item['product_id'];
            $quantity = $item['quantity'];
            $price = $item['price'];
            $stmtItem->bind_param("sssid", $itemId, $orderId, $productId, $quantity, $price);
            if (!$stmtItem->execute()) {
                throw new Exception("Execute statement failed: " . $stmtItem->error);
            }
        }
        $stmtItem->close();

        return $orderId;
    }

    public function processCheckout(string $userId, array $cartItems, array $billingAddress, array $shippingAddress, float $paymentAmount, string $paymentMethod): array
    {
        $this->db->begin_transaction();
        try {
            // Validate stock levels before creating the order
            $stockManager = new StockManager($this->db);
            foreach ($cartItems as $item) {
                $availableStock = $stockManager->getStock($item['product_id']);
                if ($availableStock < $item['quantity']) {
                    throw new Exception("Nicht genügend Bestand für Produkt-ID {$item['product_id']}. Verfügbar: {$availableStock}, benötigt: {$item['quantity']}.");
                }
            }
            $orderId = $this->createOrder($userId, $cartItems);

            $addressManager = new CustomerAddressManager($this->db);

            if (method_exists($addressManager, 'setBillingAddress')) {
                $addressManager->setBillingAddress(
                    $userId,
                    $billingAddress['street'],
                    $billingAddress['city'],
                    $billingAddress['postal_code'],
                    $billingAddress['country']
                );
            } else {
                throw new Exception("CustomerAddressManager::setBillingAddress() not implemented.");
            }

            if (method_exists($addressManager, 'setShippingAddress')) {
                $addressManager->setShippingAddress(
                    $userId,
                    $shippingAddress['street'],
                    $shippingAddress['city'],
                    $shippingAddress['postal_code'],
                    $shippingAddress['country']
                );
            } else {
                throw new Exception("CustomerAddressManager::setShippingAddress() not implemented.");
            }

            $paymentManager = new PaymentManager($this->db);
            $paymentId = $paymentManager->createPayment($orderId, $paymentAmount, $paymentMethod);

            // Set payment status to 'completed'
            $stmtUpdate = $this->db->prepare("UPDATE payments SET status = 'completed' WHERE id = ?");
            if (!$stmtUpdate) {
                throw new Exception("Prepare statement failed: " . $this->db->error);
            }
            $stmtUpdate->bind_param("s", $paymentId);
            if (!$stmtUpdate->execute()) {
                $stmtUpdate->close();
                throw new Exception("Execute statement failed: " . $stmtUpdate->error);
            }
            $stmtUpdate->close();

            // Set order status to 'completed'
            $stmtOrderUpdate = $this->db->prepare("UPDATE orders SET status = 'completed' WHERE id = ?");
            if (!$stmtOrderUpdate) {
                throw new Exception("Prepare statement failed: " . $this->db->error);
            }
            $stmtOrderUpdate->bind_param("s", $orderId);
            if (!$stmtOrderUpdate->execute()) {
                $stmtOrderUpdate->close();
                throw new Exception("Execute statement failed: " . $stmtOrderUpdate->error);
            }
            $stmtOrderUpdate->close();

            $stockManager = new StockManager($this->db);
            foreach ($cartItems as $item) {
                $stockManager->decreaseStock($item['product_id'], $item['quantity']);
            }

            $this->db->commit();
            return [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'status' => 'completed'
            ];
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function generateUuid(): string
    {
        // Generate a version 4 UUID
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // set version to 0100
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
