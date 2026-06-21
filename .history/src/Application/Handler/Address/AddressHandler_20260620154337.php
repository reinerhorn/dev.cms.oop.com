<?php
declare(strict_types=1);

namespace CMS\Application\Handler\Adress;

use CMS\Application\Interface\CrudHandlerInterface;
use RuntimeException;

final class AdressHandler implements CrudHandlerInterface
{
    public function __construct(
        private \mysqli $db
    ) {}

    /**
     * Ein zentraler Handler für:
     * - speichern
     * - update
     * - löschen
     * - cancel
     */
    public function handle(array $postData, array $pageMeta = []): array
    {
        $action = trim((string)($postData['action'] ?? ''));
        $id     = trim((string)($postData['id'] ?? ''));

        return match ($action) {
            'adress_save'   => $this->handleSave($postData, $id),
            'adress_update' => $this->handleUpdate($postData, $id),
            'adress_delete' => $this->handleDelete($id),
            'adress_cancel' => $this->success(
                'Abgebrochen',
                $this->redirectBack()
            ),
            default => throw new RuntimeException(
                'Unbekannte Adress-Aktion: ' . $action
            ),
        };
    }

    public function load(string $id): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                user_id,
                type,
                name,
                street,
                postal_code,
                city,
                country,
                phone,
                created_at
            FROM address
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'Adress load prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param('s', $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        $stmt->close();

        return $row ?: [];
    }

    public function save(array $data): string
    {
        $this->validate($data);

        $id = $this->uuid();

        $userId     = trim((string)$data['user_id']);
        $type       = trim((string)$data['type']);
        $name       = trim((string)$data['name']);
        $street     = trim((string)$data['street']);
        $postalCode = trim((string)$data['postal_code']);
        $city       = trim((string)$data['city']);
        $country    = trim((string)$data['country']);
        $phone      = $this->nullableString($data['phone'] ?? null);

        $stmt = $this->db->prepare("
            INSERT INTO address
            (
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
            throw new RuntimeException(
                'Adress save prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param(
            'sssssssss',
            $id,
            $userId,
            $type,
            $name,
            $street,
            $postalCode,
            $city,
            $country,
            $phone
        );

        if (!$stmt->execute()) {
            throw new RuntimeException(
                'Adress speichern fehlgeschlagen: ' . $stmt->error
            );
        }

        $stmt->close();

        return $id;
    }

    public function update(string $id, array $data): bool
    {
        if ($id === '') {
            throw new RuntimeException('Adress-ID für Update fehlt');
        }

        $this->validate($data);

        $userId     = trim((string)$data['user_id']);
        $type       = trim((string)$data['type']);
        $name       = trim((string)$data['name']);
        $street     = trim((string)$data['street']);
        $postalCode = trim((string)$data['postal_code']);
        $city       = trim((string)$data['city']);
        $country    = trim((string)$data['country']);
        $phone      = $this->nullableString($data['phone'] ?? null);

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
            throw new RuntimeException(
                'Adress update prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param(
            'sssssssss',
            $userId,
            $type,
            $name,
            $street,
            $postalCode,
            $city,
            $country,
            $phone,
            $id
        );

        $success = $stmt->execute();

        if (!$success) {
            throw new RuntimeException(
                'Adress aktualisieren fehlgeschlagen: ' . $stmt->error
            );
        }

        $stmt->close();

        return true;
    }

    public function delete(string $id): bool
    {
        if ($id === '') {
            throw new RuntimeException('Adress-ID zum Löschen fehlt');
        }

        $stmt = $this->db->prepare("
            DELETE FROM address
            WHERE id = ?
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'Adress delete prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param('s', $id);

        $success = $stmt->execute();

        if (!$success) {
            throw new RuntimeException(
                'Adress löschen fehlgeschlagen: ' . $stmt->error
            );
        }

        $stmt->close();

        return true;
    }

    private function handleSave(array $postData, string $id): array
    {
        /*
         * Ein Button "Speichern" kann auch einen Datensatz aktualisieren,
         * wenn eine id mitgeschickt wird.
         */
        if ($id !== '') {
            $this->update($id, $postData);

            return $this->success(
                'Adresse aktualisiert',
                $this->redirectBack()
            );
        }

        $newId = $this->save($postData);

        return [
            'status'   => 'ok',
            'success'  => true,
            'message'  => 'Adresse gespeichert',
            'id'       => $newId,
            'redirect' => $this->redirectBack(),
            'errors'   => [],
        ];
    }

    private function handleUpdate(array $postData, string $id): array
    {
        $this->update($id, $postData);

        return $this->success(
            'Adresse aktualisiert',
            $this->redirectBack()
        );
    }

    private function handleDelete(string $id): array
    {
        $this->delete($id);

        return $this->success(
            'Adresse gelöscht',
            $this->redirectBack()
        );
    }

    private function validate(array $data): void
    {
        $required = [
            'user_id'     => 'Benutzer',
            'type'        => 'Adresstyp',
            'name'        => 'Name',
            'street'      => 'Straße',
            'postal_code' => 'Postleitzahl',
            'city'        => 'Stadt',
            'country'     => 'Land',
        ];

        foreach ($required as $field => $label) {
            if (trim((string)($data[$field] ?? '')) === '') {
                throw new RuntimeException(
                    'Pflichtfeld fehlt: ' . $label
                );
            }
        }

        $type = trim((string)$data['type']);

        if (!in_array($type, ['billing', 'shipping'], true)) {
            throw new RuntimeException(
                'Ungültiger Adresstyp. Erlaubt: billing oder shipping'
            );
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function success(string $message, string $redirect): array
    {
        return [
            'status'   => 'ok',
            'success'  => true,
            'message'  => $message,
            'redirect' => $redirect,
            'errors'   => [],
        ];
    }

    private function redirectBack(): string
    {
        return $_SERVER['HTTP_REFERER'] ?? '/';
    }

    private function uuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}
