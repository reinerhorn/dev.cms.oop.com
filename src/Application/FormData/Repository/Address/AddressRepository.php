<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Repository\Address;

final class AddressRepository
{
    public function __construct(
        private \mysqli $db
    ) {
    }


    public function find(string $id): array
    {
        $stmt = $this->db->prepare("
    SELECT *
    FROM address
    WHERE id = ?
");

if (!$stmt) {
    throw new \RuntimeException(
        $this->db->error
    );
}

$stmt->bind_param(
    "s",
    $id
);

if (!$stmt->execute()) {
    throw new \RuntimeException(
        $stmt->error
    );
}

$result = $stmt->get_result();

return $result->fetch_assoc() ?? [];
    }


    public function findAll(): array
    {
        $result = $this->db->query(
    "SELECT * FROM address"
);

if (!$result) {
    throw new \RuntimeException(
        $this->db->error
    );
}

return $result->fetch_all(
    MYSQLI_ASSOC
);
    }


    public function insert(array $data): string
    {
        $id = bin2hex(random_bytes(16));

$user_id = $data['user_id'] ?? '';
$type = $data['type'] ?? '';
$name = $data['name'] ?? '';
$street = $data['street'] ?? '';
$postal_code = $data['postal_code'] ?? '';
$city = $data['city'] ?? '';
$country = $data['country'] ?? '';
$phone = $data['phone'] ?? '';

$stmt = $this->db->prepare("
    INSERT INTO address (
        id,
        user_id,
        type,
        name,
        street,
        postal_code,
        city,
        country,
        phone
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    throw new \RuntimeException(
        $this->db->error
    );
}

$stmt->bind_param(
    "sssssssss",
    $id,
    $user_id,
    $type,
    $name,
    $street,
    $postal_code,
    $city,
    $country,
    $phone
);

if (!$stmt->execute()) {
    throw new \RuntimeException(
        $stmt->error
    );
}

return $id;
    }


    public function update(string $id, array $data): bool
    {
        $user_id = $data['user_id'] ?? '';
$type = $data['type'] ?? '';
$name = $data['name'] ?? '';
$street = $data['street'] ?? '';
$postal_code = $data['postal_code'] ?? '';
$city = $data['city'] ?? '';
$country = $data['country'] ?? '';
$phone = $data['phone'] ?? '';

$stmt = $this->db->prepare("
    UPDATE address
    SET
        user_id = ?,
        type = ?,
        name = ?,
        street = ?,
        postal_code = ?,
        city = ?,
        country = ?,
        phone = ?
    WHERE id = ?
");

if (!$stmt) {
    throw new \RuntimeException(
        $this->db->error
    );
}

$stmt->bind_param(
    "sssssssss",
    $user_id,
    $type,
    $name,
    $street,
    $postal_code,
    $city,
    $country,
    $phone,
    $id
);

if (!$stmt->execute()) {
    throw new \RuntimeException(
        $stmt->error
    );
}

return true;
    }


    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("
    DELETE FROM address
    WHERE id = ?
");

if (!$stmt) {
    throw new \RuntimeException($this->db->error);
}

$stmt->bind_param(
    "s",
    $id
);

if (!$stmt->execute()) {
    throw new \RuntimeException($stmt->error);
}

return true;
    }
}

