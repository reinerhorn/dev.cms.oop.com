<?php

class PaymentManager
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Zahlung anlegen
     */
    public function createPayment(string $orderId, float $amount, string $method, string $status = 'pending'): string
    {
        $paymentId = $this->generateUuid();

        $stmt = $this->db->prepare("
            INSERT INTO payments (id, order_id, amount, method, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('ssdss', $paymentId, $orderId, $amount, $method, $status);

        if (!$stmt->execute()) {
            throw new Exception("Fehler beim Anlegen der Zahlung: " . $stmt->error);
        }

        return $paymentId;
    }

    /**
     * Zahlung nach ID abrufen
     */
    public function getPaymentById(string $paymentId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->bind_param('s', $paymentId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc() ?: null;
    }

    /**
     * Zahlungen zu einer Bestellung abrufen
     */
    public function getPaymentsByOrderId(string $orderId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY transaction_date DESC");
        $stmt->bind_param('s', $orderId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Zahlungsstatus aktualisieren
     */
    public function updatePaymentStatus(string $paymentId, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE payments SET status = ? WHERE id = ?");
        $stmt->bind_param('ss', $status, $paymentId);

        $success = $stmt->execute();

        if ($success && $status === 'completed') {
            $stmtOrderId = $this->db->prepare("SELECT order_id FROM payments WHERE id = ?");
            $stmtOrderId->bind_param('s', $paymentId);
            $stmtOrderId->execute();
            $result = $stmtOrderId->get_result();
            $payment = $result->fetch_assoc();
            $stmtOrderId->close();

            if ($payment && isset($payment['order_id'])) {
                $orderId = $payment['order_id'];
                $stmtUpdateOrder = $this->db->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
                $stmtUpdateOrder->bind_param('s', $orderId);
                $stmtUpdateOrder->execute();
                $stmtUpdateOrder->close();
            }
        }

        return $success;
    }

    /**
     * Hilfsfunktion zur UUID-Generierung
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
