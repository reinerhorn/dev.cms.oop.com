<?php
class CustomerAddressManager
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function setBillingAddress(string $userId, string $street, string $city, string $postalCode, string $country): string
    {
        return $this->setAddress($userId, 'billing', $street, $city, $postalCode, $country);
    }

    public function setShippingAddress(string $userId, string $street, string $city, string $postalCode, string $country): string
    {
        return $this->setAddress($userId, 'shipping', $street, $city, $postalCode, $country);
    }

    private function setAddress(string $userId, string $type, string $street, string $city, string $postalCode, string $country): string
    {
        $id = $this->generateUuid();
        $stmt = $this->db->prepare("
            INSERT INTO customer_addresses (id, user_id, type, street, city, postal_code, country)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssssss", $id, $userId, $type, $street, $city, $postalCode, $country);
        $stmt->execute();
        return $id;
    }

    public function getAddressesByUserId(string $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM customer_addresses WHERE user_id = ?");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $addresses = [];
        while ($row = $result->fetch_assoc()) {
            $addresses[$row['type']] = $row;
        }
        return $addresses;
    }

    public function deleteAddressesByUserId(string $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM customer_addresses WHERE user_id = ?");
        $stmt->bind_param("s", $userId);
        return $stmt->execute();
    }

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
