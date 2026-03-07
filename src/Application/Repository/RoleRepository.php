<?php
declare(strict_types=1);

namespace CMS\Application\Repository;

use mysqli;

final class RoleRepository
{
    public function __construct(
        private mysqli $db
    ) {}

    public function getDefaultPageId(string $roleId): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT default_page_id FROM roles WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('s', $roleId);
        $stmt->execute();

        return $stmt->get_result()->fetch_column() ?: null;
    }
}
