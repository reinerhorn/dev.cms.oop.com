<?php
 

namespace CMS\Application\FormData\Handler\Address;

use CMS\Application\Interface\CrudHandlerInterface;

final class AddressHandler implements CrudHandlerInterface
{
    public function __construct(
        private \mysqli $db
    ) {}

    public function handle(array $postData, array $pageMeta = []): array
    {
        $action = $postData['action'] ?? '';

        $id = trim((string)(
            $postData['id']
            ?? $postData['address__id']
            ?? $postData['address_load_id']
            ?? ''
        ));

        $data = [
            'user_id' => $postData['address__user_id'] ?? '' ,
            'type' => $postData['address__type'] ?? '' ,
            'name' => $postData['address__name'] ?? '' ,
            'street' => $postData['address__street'] ?? '' ,
            'postal_code' => $postData['address__postal_code'] ?? '' ,
            'city' => $postData['address__city'] ?? '' ,
            'country' => $postData['address__country'] ?? '' ,
            'phone' => $postData['address__phone'] ?? '' ,
        ];

        if (str_contains($action, 'delete')) {
            $this->delete($id);

            return [
                'success' => true,
                'message' => 'Address gelöscht'
            ];
        }

        if ($id !== '') {
            $this->update($id, $data);

            return [
                'success' => true,
                'message' => 'Address aktualisiert'
            ];
        }

        $newId = $this->save($data);

        return [
            'success' => true,
            'message' => 'Address gespeichert',
            'id' => $newId
        ];
    }

    public function load(string $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM address WHERE id = ?"
        );

        $stmt->bind_param("s", $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?? [];
    }

    public function save(array $data): string
    {
        $id = $this->uuid();

$stmt = $this->db->prepare("
    INSERT INTO address (
                user_id,
                type,
                name,
                street,
                postal_code,
                city,
                country,
                phone
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssssssss",
    $data['user_id'],
            $data['type'],
            $data['name'],
            $data['street'],
            $data['postal_code'],
            $data['city'],
            $data['country'],
            $data['phone']
);

if (!$stmt->execute()) {
    throw new \RuntimeException($stmt->error);
}

        return $id;
    }

    public function update(string $id, array $data): bool
    {
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

$stmt->bind_param(
    "sssssssss",
    $data['user_id'],
    $data['type'],
    $data['name'],
    $data['street'],
    $data['postal_code'],
    $data['city'],
    $data['country'],
    $data['phone'],
    $id
);

if (!$stmt->execute()) {
    throw new \RuntimeException($stmt->error);
}
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM address WHERE id = ?"
        );

        $stmt->bind_param("s", $id);

        return $stmt->execute();
    }

    private function uuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}
