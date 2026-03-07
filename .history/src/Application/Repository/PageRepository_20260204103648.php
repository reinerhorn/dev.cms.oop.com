<?php
declare(strict_types=1);

namespace CMS\Application\Repository;

use mysqli;

final class PageRepository
{
    public function __construct(
        private mysqli $db
    ) {}

    public function getSlugByPageId(string $pageId): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT slug FROM page WHERE page_uuid = ? AND enabled = 1 LIMIT 1'
        );
        $stmt->bind_param('s', $pageId);
        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();

        return $result['slug'] ?? null;
    }
}