<?php
declare(strict_types=1);

namespace CMS\Application\Handler\Footer;

use CMS\Application\Interface\CrudHandlerInterface;
final class FooterHandler implements CrudHandlerInterface
{
    public function __construct(
        private \mysqli $db
    ) {}

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
            INSERT INTO footer (id, headline, link, label, css, context_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssss",
            $id,
            $data['headline'],
            $data['link'],
            $data['label'],
            $data['css'],
            $data['context_id']
        );

        $stmt->execute();

        return $id;
    }

    public function update(string $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE footer
            SET headline = ?, link = ?, label = ?, css = ?, context_id = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ssssss",
            $data['headline'],
            $data['link'],
            $data['label'],
            $data['css'],
            $data['context_id'],
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