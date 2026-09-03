<?php
declare(strict_types=1);

namespace CMS\Application\Handler\Footer;

use CMS\Application\Interface\CrudHandlerInterface;
final class FooterImagesHandler implements CrudHandlerInterface
{
    public function __construct(
        private \mysqli $db
    ) {}

    public function handle(array $postData, array $pageMeta = []): array
    {
        $action = $postData['action'] ?? '';

        $id = trim((string)(
            $postData['id']
            ?? $postData['footer_images__id']
            ?? $postData['footer_images_load_id']
            ?? ''
        ));

        $data = [
            'footer_id'  => $postData['footer_images__footer_id'] ?? '',
            'image_url'  => $postData['footer_images__image_url'] ?? '',
            'link_url'   => $postData['footer_images__link_url'] ?? '',
            'alt_text'   => $postData['footer_images__alt_text'] ?? '',
            'sort_order' => (int)($postData['footer_images__sort_order'] ?? 0),
        ];

        if (str_contains($action, 'delete')) {
            $this->delete($id);

            return [
                'success' => true,
                'message' => 'Footer Image gelöscht'
            ];
        }

        if ($id !== '') {
            $this->update($id, $data);

            return [
                'success' => true,
                'message' => 'Footer Image aktualisiert'
            ];
        }

        $newId = $this->save($data);

        return [
            'success' => true,
            'message' => 'Footer Image gespeichert',
            'id' => $newId
        ];
    }

    public function load(string $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM footer_images WHERE id = ?");
        if (!$stmt) {
            throw new \RuntimeException($this->db->error);
        }
        $stmt->bind_param("s", $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?? [];
    }

    public function save(array $data): string
    {
        $id = $this->uuid();

        $stmt = $this->db->prepare("
            INSERT INTO footer_images (
                id,
                footer_id,
                image_url,
                link_url,
                alt_text,
                sort_order
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            throw new \RuntimeException($this->db->error);
        }

        $stmt->bind_param(
            "sssssi",
            $id,
            $data['footer_id'],
            $data['image_url'],
            $data['link_url'],
            $data['alt_text'],
            $data['sort_order']
        );

        if (!$stmt->execute()) {
            throw new \RuntimeException($stmt->error);
        }

        return $id;
    }

    public function update(string $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE footer_images
            SET footer_id = ?,
                image_url = ?,
                link_url = ?,
                alt_text = ?,
                sort_order = ?
            WHERE id = ?
        ");
        if (!$stmt) {
            throw new \RuntimeException($this->db->error);
        }

        $stmt->bind_param(
            "ssssis",
            $data['footer_id'],
            $data['image_url'],
            $data['link_url'],
            $data['alt_text'],
            $data['sort_order'],
            $id
        );

        if (!$stmt->execute()) {
            throw new \RuntimeException($stmt->error);
        }

        return true;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM footer_images WHERE id = ?");
        if (!$stmt) {
            throw new \RuntimeException($this->db->error);
        }
        $stmt->bind_param("s", $id);

        if (!$stmt->execute()) {
            throw new \RuntimeException($stmt->error);
        }

        return true;
    }

    private function uuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}