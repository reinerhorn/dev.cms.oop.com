<?php
class OrderService
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
        $stmtItem = $this->db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        if (!$stmtItem) {
            throw new Exception("Prepare statement failed: " . $this->db->error);
        }
        foreach ($cartItems as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];
            $price = $item['price'];
            $stmtItem->bind_param("ssid", $orderId, $productId, $quantity, $price);
            if (!$stmtItem->execute()) {
                throw new Exception("Execute statement failed: " . $stmtItem->error);
            }
        }
        $stmtItem->close();

        return $orderId;
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
