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
    /**
     * Lädt alle Buttons für eine Page (UI-Konfiguration)
     * - sortiert
     * - nur aktive Buttons
     * - KEINE Business-Logik
     */
    public function getButtonsByPageUuid(string $pageUuid): array
    {
        $stmt = $this->db->prepare(
            "
            SELECT
                b.button_id,
                b.label_key,
                b.action,
                b.button_type,
                b.variant,
                b.confirm_required,
                pb.form_id,
                pb.sort_order
            FROM page_button pb
            JOIN ui_button b
                ON b.button_id = pb.button_id
            WHERE pb.page_uuid = ?
              AND b.enabled = 1
            ORDER BY pb.sort_order ASC
            "
        );

        $stmt->bind_param('s', $pageUuid);
        $stmt->execute();

        $result = $stmt->get_result();
        $buttons = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $buttons[] = $row;
            }
        }

        $stmt->close();

        return $buttons;
    }
}