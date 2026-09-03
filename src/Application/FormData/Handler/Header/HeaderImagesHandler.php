<?php
declare(strict_types=1);

namespace CMS\Application\Handler\Header;

use CMS\Application\Interface\CrudHandlerInterface;

final class HeaderImagesHandler implements CrudHandlerInterface
{
    public function __construct(
        private \mysqli $db
    ) {}

    public function handle(array $postData, array $pageMeta = []): array
    {
        $action = $postData['action'] ?? '';

        $id = trim((string)($postData['id'] ?? ''));

        switch ($action) {

            case 'header_images_save':

                if ($id !== '') {
                    $this->update($id, $postData);

                    return [
                        'status'   => 'ok',
                        'message'  => 'Headerbild aktualisiert',
                        'redirect' => $_SERVER['HTTP_REFERER'] ?? '/'
                    ];
                }

                $newId = $this->save($postData);

                return [
                    'status'   => 'ok',
                    'message'  => 'Headerbild gespeichert',
                    'id'       => $newId,
                    'redirect' => $_SERVER['HTTP_REFERER'] ?? '/'
                ];

            case 'header_images_delete':

                if ($id !== '') {
                    $this->delete($id);
                }

                return [
                    'status'   => 'ok',
                    'message'  => 'Headerbild gelöscht',
                    'redirect' => $_SERVER['HTTP_REFERER'] ?? '/'
                ];
        }

        return [
            'status'  => 'error',
            'message' => 'Unbekannte Aktion: ' . $action
        ];
    }

    public function load(string $id): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM header_images
            WHERE id = ?
        ");

        $stmt->bind_param('s', $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?: [];
    }

    public function save(array $data): string
    {
        $id = $this->uuid();

        $stmt = $this->db->prepare("
            INSERT INTO header_images
            (
                id,
                header_id,
                image_url,
                link_url,
                alt_text,
                sort_order
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            'sssssi',
            $id,
            $data['header_id'],
            $data['image_url'],
            $data['link_url'],
            $data['alt_text'],
            $data['sort_order']
        );

        $stmt->execute();

        return $id;
    }

    public function update(string $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE header_images
            SET
                header_id = ?,
                image_url = ?,
                link_url = ?,
                alt_text = ?,
                sort_order = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            'ssssis',
            $data['header_id'],
            $data['image_url'],
            $data['link_url'],
            $data['alt_text'],
            $data['sort_order'],
            $id
        );

        return $stmt->execute();
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM header_images
            WHERE id = ?
        ");

        $stmt->bind_param('s', $id);

        return $stmt->execute();
    }

    private function uuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}
