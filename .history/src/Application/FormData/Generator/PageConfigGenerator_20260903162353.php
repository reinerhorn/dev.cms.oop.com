<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class PageConfigGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    /**
     * Erstellt eine page_config-Verknüpfung für eine bestehende Page.
     *
     * Verknüpft:
     * - Page
     * - Plugin
     * - Plugin-Content / Formular
     */
    public function generate(array $config, array $plugin, array $jsonForm): array
    {
        $pageUuid = trim((string) ($config['page'] ?? ''));

        if ($pageUuid === '') {
            throw new RuntimeException(
                'PageConfigGenerator: Keine Page angegeben.'
            );
        }

        $pluginUuid = trim((string) ($plugin['plugin_uuid'] ?? ''));

        if ($pluginUuid === '') {
            throw new RuntimeException(
                'PageConfigGenerator: Keine Plugin-UUID vorhanden.'
            );
        }

        $pluginContentUuid = trim(
            (string) ($jsonForm['content_uuid'] ?? '')
        );

        if ($pluginContentUuid === '') {
            throw new RuntimeException(
                'PageConfigGenerator: Keine Formular-Content-UUID vorhanden.'
            );
        }

        /*
         * Page laden.
         *
         * fk_page_slug wird bewusst aus der DB genommen.
         */
        $page = $this->loadPage($pageUuid);

        /*
         * Nächsten freien Index ermitteln.
         *
         * Existiert bereits:
         *   idx 0
         *   idx 1
         *
         * dann wird:
         *   idx 2
         *
         * verwendet.
         */
        $idx = $this->nextIndex($pageUuid);

        $pageConfigUuid = $this->uuid();

        $contentLabel = null;

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
                'PageConfigGenerator prepare fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            'sisssss',
            $pageConfigUuid,
            $idx,
            $pageUuid,
            $page['slug'],
            $pluginUuid,
            $pluginContentUuid,
            $contentLabel
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'PageConfigGenerator konnte page_config nicht speichern: '
                . $error
            );
        }

        $stmt->close();

        return [
            'success' => true,
            'page_config_uuid' => $pageConfigUuid,
            'idx' => $idx,
            'fk_page_uuid' => $pageUuid,
            'fk_page_slug' => $page['slug'],
            'fk_plugin_uuid' => $pluginUuid,
            'plugin_content_uuid' => $pluginContentUuid,
            'content_label' => $contentLabel,
        ];
    }

    /**
     * Lädt eine vorhandene Page.
     */
    private function loadPage(string $pageUuid): array
    {
        $stmt = $this->db->prepare("
            SELECT
                page_uuid,
                slug
            FROM page
            WHERE page_uuid = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'PageConfigGenerator Page-Lookup prepare fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param('s', $pageUuid);
        $stmt->execute();

        $result = $stmt->get_result();
        $page = $result ? $result->fetch_assoc() : null;

        $stmt->close();

        if (!$page) {
            throw new RuntimeException(
                'PageConfigGenerator: Page nicht gefunden: '
                . $pageUuid
            );
        }

        return $page;
    }

    /**
     * Ermittelt den nächsten freien idx für eine Page.
     */
    private function nextIndex(string $pageUuid): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(idx), -1) + 1 AS next_idx
            FROM page_config
            WHERE fk_page_uuid = ?
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'PageConfigGenerator Index-Lookup prepare fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param('s', $pageUuid);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        $stmt->close();

        return (int) ($row['next_idx'] ?? 0);
    }

    /**
     * UUID v4.
     */
    private function uuid(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(
            ord($data[6]) & 0x0f | 0x40
        );

        $data[8] = chr(
            ord($data[8]) & 0x3f | 0x80
        );

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex($data), 4)
        );
    }
}
