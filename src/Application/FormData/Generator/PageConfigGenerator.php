<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;
use Throwable;

final class PageConfigGenerator
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Erstellt einen page_config-Eintrag.
     *
     * Neue Page:
     *   idx = 0
     *
     * Bestehende Page:
     *   idx = MAX(idx) + 1
     *
     * @param array<string,mixed> $config
     *
     * @return array<string,mixed>
     */
    public function generate(array $config): array
    {
        $pageUuid = $this->stringValue(
            $config['page_uuid'] ?? ''
        );

        $pluginUuid = $this->stringValue(
            $config['plugin_uuid'] ?? ''
        );

        $pluginContentUuid = $this->stringValue(
            $config['plugin_content_uuid'] ?? ''
        );

        $contentLabel = $this->stringValue(
            $config['content_label'] ?? ''
        );

        if ($pageUuid === '') {
            throw new RuntimeException(
                'PageConfigGenerator: page_uuid fehlt.'
            );
        }

        if ($pluginUuid === '') {
            throw new RuntimeException(
                'PageConfigGenerator: plugin_uuid fehlt.'
            );
        }

        if ($pluginContentUuid === '') {
            throw new RuntimeException(
                'PageConfigGenerator: plugin_content_uuid fehlt.'
            );
        }

        /*
         * Page laden.
         *
         * Dadurch ermitteln wir den tatsächlich gültigen Slug.
         */
        $page = $this->loadPage(
            $pageUuid
        );

        /*
         * Nächsten Index ermitteln.
         *
         * Neue Page:
         *   MAX nicht vorhanden => 0
         *
         * Bestehende Page:
         *   MAX + 1
         */
        $idx = $this->nextIndex(
            $pageUuid
        );

        $pageConfigUuid = $this->uuidV4();

        $this->db->begin_transaction();

        try {
            $this->insert(
                pageConfigUuid: $pageConfigUuid,
                idx: $idx,
                pageUuid: $page['page_uuid'],
                pageSlug: $page['slug'],
                pluginUuid: $pluginUuid,
                pluginContentUuid: $pluginContentUuid,
                contentLabel: $contentLabel
            );

            $this->db->commit();

            return [
                'success' => true,

                'page_config_uuid' =>
                    $pageConfigUuid,

                'idx' =>
                    $idx,

                'fk_page_uuid' =>
                    $page['page_uuid'],

                'fk_page_slug' =>
                    $page['slug'],

                'fk_plugin_uuid' =>
                    $pluginUuid,

                'plugin_content_uuid' =>
                    $pluginContentUuid,

                'content_label' =>
                    $contentLabel,
            ];
        } catch (Throwable $e) {
            $this->db->rollback();

            throw $e;
        }
    }

    /**
     * Page laden.
     *
     * @return array<string,string>
     */
    private function loadPage(
        string $pageUuid
    ): array {
        $sql = '
            SELECT
                page_uuid,
                slug
            FROM page
            WHERE page_uuid = ?
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'PageConfigGenerator: Prepare Page-Abfrage fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $pageUuid
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'PageConfigGenerator: Page-Abfrage fehlgeschlagen: '
                . $error
            );
        }

        $result = $stmt->get_result();

        if (
            $result === false
            || $result->num_rows === 0
        ) {
            $stmt->close();

            throw new RuntimeException(
                sprintf(
                    'PageConfigGenerator: Page "%s" wurde nicht gefunden.',
                    $pageUuid
                )
            );
        }

        $row = $result->fetch_assoc();

        $stmt->close();

        return [
            'page_uuid' =>
                (string) $row['page_uuid'],

            'slug' =>
                (string) $row['slug'],
        ];
    }

    /**
     * Ermittelt den nächsten idx für die Page.
     */
    private function nextIndex(
        string $pageUuid
    ): int {
        $sql = '
            SELECT
                COALESCE(MAX(idx), -1) + 1 AS next_idx
            FROM page_config
            WHERE fk_page_uuid = ?
        ';

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'PageConfigGenerator: Prepare Index-Abfrage fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $pageUuid
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'PageConfigGenerator: Index-Abfrage fehlgeschlagen: '
                . $error
            );
        }

        $result = $stmt->get_result();

        if ($result === false) {
            $stmt->close();

            throw new RuntimeException(
                'PageConfigGenerator: Index-Ergebnis konnte nicht gelesen werden.'
            );
        }

        $row = $result->fetch_assoc();

        $stmt->close();

        return (int) (
            $row['next_idx'] ?? 0
        );
    }

    /**
     * Insert page_config.
     */
    private function insert(
        string $pageConfigUuid,
        int $idx,
        string $pageUuid,
        string $pageSlug,
        string $pluginUuid,
        string $pluginContentUuid,
        string $contentLabel
    ): void {
        $sql = '
            INSERT INTO page_config (
                page_config_uuid,
                idx,
                fk_page_uuid,
                fk_page_slug,
                fk_plugin_uuid,
                plugin_content_uuid,
                content_label
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ';

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'PageConfigGenerator: Prepare Insert fehlgeschlagen: '
                . $this->db->error
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
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'PageConfigGenerator: page_config konnte nicht angelegt werden: '
                . $error
            );
        }

        $stmt->close();
    }

    /**
     * String normalisieren.
     */
    private function stringValue(
        mixed $value
    ): string {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return trim(
                (string) $value
            );
        }

        return '';
    }

    /**
     * UUID v4.
     */
    private function uuidV4(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(
            (ord($data[6]) & 0x0f) | 0x40
        );

        $data[8] = chr(
            (ord($data[8]) & 0x3f) | 0x80
        );

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 6))
        );
    }
}