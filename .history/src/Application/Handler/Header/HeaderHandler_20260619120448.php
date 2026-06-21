<?php
declare(strict_types=1);

namespace CMS\Application\Handler\Header;

use CMS\Application\Interface\CrudHandlerInterface;

final class HeaderHandler implements CrudHandlerInterface
{
    public function __construct(
        private \mysqli $db
    ) {}

    public function handle(array $postData, array $pageMeta = []): array
    {
        $action = $postData['action'] ?? '';

        $id = trim((string)(
            $postData['id']
            ?? $postData['header__id']
            ?? $postData['header_load_id']
            ?? ''
        ));

        $data = [
            'headline' => trim((string)($postData['header__headline'] ?? '')),
            'link'     => trim((string)($postData['header__link'] ?? '')),
            'label'    => trim((string)($postData['header__label'] ?? '')),
            'version'  => trim((string)($postData['header__version'] ?? '')),
            'css'      => trim((string)($postData['header__css'] ?? '')),
            'context_id' => trim((string)($postData['header__context_id'] ?? '')),
            'fk_translation_placeholder' =>
                trim((string)($postData['header__fk_translation_placeholder'] ?? '')),
        ];

        if (str_contains($action, 'delete')) {

            if ($id !== '') {
                $this->delete($id);
            }

            return [
                'success' => true,
                'message' => 'Header gelöscht'
            ];
        }

        if ($id !== '') {

            $this->update($id, $data);

            return [
                'success' => true,
                'message' => 'Header aktualisiert'
            ];
        }

        $newId = $this->save($data);

        return [
            'success' => true,
            'message' => 'Header gespeichert',
            'id' => $newId
        ];
    }

    public function load(string $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM header WHERE id = ?"
        );

        $stmt->bind_param("s", $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?? [];
    }

    public function save(array $data): string
    {
        $id = $this->uuid();

        $stmt = $this->db->prepare("
            INSERT INTO header (
                id,
                headline,
                link,
                label,
                version,
                css,
                context_id,
                fk_translation_placeholder
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssssss",
            $id,
            $data['headline'],
            $data['link'],
            $data['label'],
            $data['version'],
            $data['css'],
            $data['context_id'],
            $data['fk_translation_placeholder']
        );

        $stmt->execute();

        if ($stmt->error) {
            throw new \RuntimeException($stmt->error);
        }

        return $id;
    }

    public function update(string $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE header
            SET headline = ?,
                link = ?,
                label = ?,
                version = ?,
                css = ?,
                context_id = ?,
                fk_translation_placeholder = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ssssssss",
            $data['headline'],
            $data['link'],
            $data['label'],
            $data['version'],
            $data['css'],
            $data['context_id'],
            $data['fk_translation_placeholder'],
            $id
        );

        $result = $stmt->execute();

        if ($stmt->error) {
            throw new \RuntimeException($stmt->error);
        }

        return $result;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM header WHERE id = ?"
        );

        $stmt->bind_param("s", $id);

        return $stmt->execute();
    }

    private function uuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}

