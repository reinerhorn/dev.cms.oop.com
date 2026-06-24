<?php
declare(strict_types=1);

namespace CMS\Application\Handler\Page;

use CMS\Application\Interface\CrudHandlerInterface;
use RuntimeException;

final class PageConfigHandler implements CrudHandlerInterface
{
    public function __construct(
        private \mysqli $db
    ) {
    }

    public function load(string $id): array
    {
        $stmt = $this->db->prepare("
            SELECT
                page_config_uuid,
                idx,
                fk_page_uuid,
                fk_page_slug,
                fk_plugin_uuid,
                plugin_content_uuid,
                content_label
            FROM page_config
            WHERE page_config_uuid = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'PageConfig load prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param('s', $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : [];

        $stmt->close();

        return $row ?: [];
    }

    public function save(array $data): string
    {
        $pageConfigUuid = $this->uuid();

        $idx = $this->requiredInt($data, 'idx');
        $pageUuid = $this->requiredString($data, 'fk_page_uuid');
        $pageSlug = $this->requiredString($data, 'fk_page_slug');

        $pluginUuid = $this->nullableString($data['fk_plugin_uuid'] ?? null);
        $pluginContentUuid = $this->nullableString(
            $data['plugin_content_uuid'] ?? null
        );

        /*
         * content_label ist nur deine Orientierung in page_config.
         * Er wird nicht für die CMS-Logik verwendet.
         */
        $contentLabel = $this->nullableString($data['content_label'] ?? null);

        $this->assertIndexAvailable($pageUuid, $idx);

        $stmt = $this->db->prepare("
            INSERT INTO page_config
            (
                page_config_uuid,
                idx,
                fk_page_uuid,
                fk_page_slug,
                fk_plugin_uuid,
                plugin_content_uuid,
                content_label
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'PageConfig save prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param(
            'sisssss',
            $pageConfigUuid,
            $idx,
            $pageUuid,
            $pageSlug,
            $pluginUuid,
            $pluginContentUuid,
            $contentLabel
        );

        if (!$stmt->execute()) {
            throw new RuntimeException(
                'PageConfig konnte nicht gespeichert werden: ' . $stmt->error
            );
        }

        $stmt->close();

        return $pageConfigUuid;
    }

    public function update(string $id, array $data): bool
    {
        $idx = $this->requiredInt($data, 'idx');
        $pageUuid = $this->requiredString($data, 'fk_page_uuid');
        $pageSlug = $this->requiredString($data, 'fk_page_slug');

        $pluginUuid = $this->nullableString($data['fk_plugin_uuid'] ?? null);
        $pluginContentUuid = $this->nullableString(
            $data['plugin_content_uuid'] ?? null
        );

        $contentLabel = $this->nullableString($data['content_label'] ?? null);

        $this->assertIndexAvailable($pageUuid, $idx, $id);

        $stmt = $this->db->prepare("
            UPDATE page_config
            SET
                idx = ?,
                fk_page_uuid = ?,
                fk_page_slug = ?,
                fk_plugin_uuid = ?,
                plugin_content_uuid = ?,
                content_label = ?
            WHERE page_config_uuid = ?
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'PageConfig update prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param(
            'issssss',
            $idx,
            $pageUuid,
            $pageSlug,
            $pluginUuid,
            $pluginContentUuid,
            $contentLabel,
            $id
        );

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM page_config
            WHERE page_config_uuid = ?
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'PageConfig delete prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param('s', $id);

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * idx darf pro Seite nur einmal vorkommen.
     *
     * Beispiel:
     * Seite A:
     * idx 0 = plaintext
     * idx 1 = formular
     * idx 2 = image
     */
    private function assertIndexAvailable(
        string $pageUuid,
        int $idx,
        ?string $currentPageConfigUuid = null
    ): void {
        if ($currentPageConfigUuid === null) {
            $stmt = $this->db->prepare("
                SELECT page_config_uuid
                FROM page_config
                WHERE fk_page_uuid = ?
                  AND idx = ?
                LIMIT 1
            ");

            if (!$stmt) {
                throw new RuntimeException(
                    'PageConfig idx-Prüfung prepare fehlgeschlagen: '
                    . $this->db->error
                );
            }

            $stmt->bind_param('si', $pageUuid, $idx);
        } else {
            $stmt = $this->db->prepare("
                SELECT page_config_uuid
                FROM page_config
                WHERE fk_page_uuid = ?
                  AND idx = ?
                  AND page_config_uuid != ?
                LIMIT 1
            ");

            if (!$stmt) {
                throw new RuntimeException(
                    'PageConfig idx-Prüfung prepare fehlgeschlagen: '
                    . $this->db->error
                );
            }

            $stmt->bind_param(
                'sis',
                $pageUuid,
                $idx,
                $currentPageConfigUuid
            );
        }

        $stmt->execute();

        $result = $stmt->get_result();
        $exists = $result && $result->fetch_assoc();

        $stmt->close();

        if ($exists) {
            throw new RuntimeException(
                'Der Index ' . $idx
                . ' ist auf dieser Seite bereits vergeben.'
            );
        }
    }

    private function requiredString(array $data, string $key): string
    {
        $value = trim((string)($data[$key] ?? ''));

        if ($value === '') {
            throw new RuntimeException(
                'Pflichtfeld fehlt: ' . $key
            );
        }

        return $value;
    }

    private function requiredInt(array $data, string $key): int
    {
        if (!isset($data[$key]) || $data[$key] === '') {
            throw new RuntimeException(
                'Pflichtfeld fehlt: ' . $key
            );
        }

        if (!is_numeric($data[$key])) {
            throw new RuntimeException(
                'Ungültiger Zahlenwert für: ' . $key
            );
        }

        return (int)$data[$key];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function uuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}