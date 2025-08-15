<?php

class CustomerManager
{
    private $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function addAddress($userId, $type, $street, $city, $postalCode, $country)
    {
        $id = $this->generateUuid();
        $stmt = $this->mysqli->prepare("
            INSERT INTO customer_addresses (id, user_id, type, street, city, postal_code, country)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("sssssss", $id, $userId, $type, $street, $city, $postalCode, $country);

        if (!$stmt->execute()) {
            throw new Exception("Error adding address: " . $stmt->error);
        }

        $stmt->close();
        return $id;
    }

    public function getAddressesByUser($userId)
    {
        $stmt = $this->mysqli->prepare("
            SELECT * 
            FROM customer_addresses 
            WHERE user_id = ? 
            ORDER BY FIELD(type, 'billing', 'shipping'), created_at ASC
        ");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $this->mysqli->error);
        }
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $addresses = $result->fetch_all(MYSQLI_ASSOC);

        $stmt->close();
        return $addresses;
    }

    public function deleteAddress($id)
    {
        $stmt = $this->mysqli->prepare("DELETE FROM customer_addresses WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $stmt->close();
    }

    public function setAddress(string $userId, string $type, array $addressData): bool
    {
        $this->mysqli->begin_transaction();
        try {
            // Delete existing addresses for the user and type
            $deleteStmt = $this->mysqli->prepare("DELETE FROM customer_addresses WHERE user_id = ? AND type = ?");
            if (!$deleteStmt) {
                $this->mysqli->rollback();
                return false;
            }
            $deleteStmt->bind_param("ss", $userId, $type);
            if (!$deleteStmt->execute()) {
                $deleteStmt->close();
                $this->mysqli->rollback();
                return false;
            }
            $deleteStmt->close();

            // Insert new address
            $id = $this->generateUuid();
            $insertStmt = $this->mysqli->prepare("
                INSERT INTO customer_addresses (id, user_id, type, street, city, postal_code, country)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$insertStmt) {
                $this->mysqli->rollback();
                return false;
            }
            $insertStmt->bind_param(
                "sssssss",
                $id,
                $userId,
                $type,
                $addressData['street'],
                $addressData['city'],
                $addressData['postal_code'],
                $addressData['country']
            );
            if (!$insertStmt->execute()) {
                $insertStmt->close();
                $this->mysqli->rollback();
                return false;
            }
            $insertStmt->close();

            $this->mysqli->commit();
            return true;
        } catch (Exception $e) {
            $this->mysqli->rollback();
            return false;
        }
    }

    private function generateUuid()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
