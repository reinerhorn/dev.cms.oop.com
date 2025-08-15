<?php

class StockManager
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Ändert den Lagerbestand eines Produkts und protokolliert die Bewegung.
     *
     * @param string $productId
     * @param int $changeQty Positive Zahl für Zugang, negative Zahl für Abgang
     * @param string $reason Grund der Änderung: purchase, sale, adjustment, return
     * @return bool
     */
    public function updateStock(string $productId, int $changeQty, string $reason): bool
    {
        // Prüfen ob Produkt existiert
        $stmt = $this->db->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->bind_param("s", $productId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return false; // Produkt nicht gefunden
        }

        $currentStock = (int) $result->fetch_assoc()['stock'];
        $newStock = $currentStock + $changeQty;

        // Lagerbestand aktualisieren
        $stmt = $this->db->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $stmt->bind_param("is", $newStock, $productId);
        $stmt->execute();

        // Bewegung protokollieren
        $stmt = $this->db->prepare("
            INSERT INTO stock_movements (id, product_id, change_qty, reason) 
            VALUES (?, ?, ?, ?)
        ");
        $movementId = $this->generateUuid();
        $stmt->bind_param("ssis", $movementId, $productId, $changeQty, $reason);
        $stmt->execute();

        return true;
    }

    /**
     * Verringert den Lagerbestand eines Produkts (Abgang).
     *
     * @param string $productId
     * @param int $quantity
     * @return bool
     */
    public function decreaseStock(string $productId, int $quantity): bool
    {
        return $this->updateStock($productId, -$quantity, 'sale');
    }

    /**
     * Erhöht den Lagerbestand eines Produkts (Zugang).
     *
     * @param string $productId
     * @param int $quantity
     * @param string $reason
     * @return bool
     */
    public function increaseStock(string $productId, int $quantity, string $reason = 'purchase'): bool
    {
        return $this->updateStock($productId, $quantity, $reason);
    }

    /**
     * Holt den aktuellen Bestand eines Produkts.
     *
     * @param string $productId
     * @return int|null
     */
    public function getStock(string $productId): ?int
    {
        $stmt = $this->db->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->bind_param("s", $productId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return (int) $row['stock'];
        }

        return null;
    }

    /**
     * Listet alle Bewegungen für ein Produkt.
     *
     * @param string $productId
     * @return array
     */
    public function getMovements(string $productId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, product_id, change_qty, reason, created_at
            FROM stock_movements
            WHERE product_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->bind_param("s", $productId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Generiert eine einfache UUID v4.
     *
     * @return string
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