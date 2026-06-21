<?php
declare(strict_types=1);

namespace CMS\Application\Handler\Footer;

use CMS\Application\Interface\CrudHandlerInterface;
final class FooterHandler implements CrudHandlerInterface
{
    public function __construct(
        private \mysqli $db
    ) {}

    public function handle(array $postData, array $pageMeta = []): array
    {
        $action = $postData['action'] ?? '';

        $id = trim((string)($postData['id'] ?? ''));

        $data = [
            'headline'   => $postData['footer__headline'] ?? $postData['headline'] ?? '',
            'link'       => $postData['footer__link'] ?? $postData['link'] ?? '',
            'label'      => $postData['footer__label'] ?? $postData['label'] ?? '',
            'css'        => $postData['footer__css'] ?? $postData['css'] ?? '',
            'context_id' => $postData['footer__context_id'] ?? $postData['context_id'] ?? '',
            'fk_translation_placeholder' => $postData['footer__fk_translation_placeholder'] ?? $postData['fk_translation_placeholder'] ?? '',
        ];
        error_log('FOOTER POST DATA: ' . print_r($data, true));

        if (str_contains($action, 'delete')) {
            $this->delete($id);

            return [
                'success' => true,
                'message' => 'Footer gelöscht'
            ];
        }

        if ($id !== '') {
            $this->update($id, $data);

            return [
                'success' => true,
                'message' => 'Footer aktualisiert'
            ];
        }

        $newId = $this->save($data);

        return [
            'success' => true,
            'message' => 'Footer gespeichert',
            'id' => $newId
        ];
    }

    public function load(string $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM footer WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?? [];
    }

    public function save(array $data): string
    {
        $id = $this->uuid();

        $stmt = $this->db->prepare("
            INSERT INTO footer (
                id,
                headline,
                link,
                label,
                css,
                context_id,
                fk_translation_placeholder
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sssssss",
            $id,
            $data['headline'],
            $data['link'],
            $data['label'],
            $data['css'],
            $data['context_id'],
            $data['fk_translation_placeholder']
        );

        $stmt->execute();

        return $id;
    }

    public function update(string $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE footer
            SET headline = ?, link = ?, label = ?, css = ?, context_id = ?, fk_translation_placeholder = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "sssssss",
            $data['headline'],
            $data['link'],
            $data['label'],
            $data['css'],
            $data['context_id'],
            $data['fk_translation_placeholder'],
            $id
        );

        return $stmt->execute();
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM footer WHERE id = ?");
        $stmt->bind_param("s", $id);

        return $stmt->execute();
    }

    private function uuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}