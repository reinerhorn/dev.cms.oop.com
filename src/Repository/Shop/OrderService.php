<?php

class OrderService
{
    private $mysqli;
    private $stockManager;

    public function __construct(mysqli $mysqli, StockManager $stockManager)
    {
        $this->mysqli = $mysqli;
        $this->stockManager = $stockManager;
    }

    public function calculateOrderTotal(array $items)
    {
        $total = 0.0;

        $stmt = $this->mysqli->prepare("SELECT price FROM products WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->mysqli->error);
        }

        try {
            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];

                $stmt->bind_param("s", $productId);
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $price = (float)$row['price'];
                    $total += $price * $quantity;
                } else {
                    throw new Exception("Product not found: " . $productId);
                }
                $result->free();
            }
        } finally {
            $stmt->close();
        }

        return $total;
    }

    public function placeOrder($userId, array $items)
    {
        $orderId = $this->generateUuid();
        $orderStmt = null;
        $itemStmt = null;

        $this->mysqli->begin_transaction();

        try {
            // Check stock availability for each product before placing the order
            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];
                $currentStock = $this->stockManager->getStock($productId);
                if ($quantity > $currentStock) {
                    throw new Exception("Not enough stock for product: " . $productId);
                }
            }

            $total = $this->calculateOrderTotal($items);

            // Insert order with total
            $orderStmt = $this->mysqli->prepare("INSERT INTO orders (id, user_id, status, total) VALUES (?, ?, 'pending', ?)");
            if (!$orderStmt) {
                throw new Exception("Prepare failed: " . $this->mysqli->error);
            }
            $orderStmt->bind_param("ssd", $orderId, $userId, $total);
            if (!$orderStmt->execute()) {
                throw new Exception("Execute failed: " . $orderStmt->error);
            }

            // Insert order items and update stock
            $itemStmt = $this->mysqli->prepare("INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)");
            if (!$itemStmt) {
                throw new Exception("Prepare failed: " . $this->mysqli->error);
            }

            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];

                $itemStmt->bind_param("ssi", $orderId, $productId, $quantity);
                if (!$itemStmt->execute()) {
                    throw new Exception("Execute failed: " . $itemStmt->error);
                }

                $this->stockManager->decreaseStock($productId, $quantity);
            }

            $this->mysqli->commit();

            return $orderId;
        } catch (Exception $e) {
            $this->mysqli->rollback();
            throw $e;
        } finally {
            if ($orderStmt !== null) {
                $orderStmt->close();
            }
            if ($itemStmt !== null) {
                $itemStmt->close();
            }
        }
    }

    public function updateOrderStatus($orderId, $status)
    {
        $stmt = $this->mysqli->prepare("UPDATE orders SET status = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->mysqli->error);
        }

        try {
            $stmt->bind_param("ss", $status, $orderId);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
        } finally {
            $stmt->close();
        }
    }

    private function generateUuid()
    {
        $data = random_bytes(16);
        // Set version to 4
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
