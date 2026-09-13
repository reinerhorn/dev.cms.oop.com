<?php
declare(strict_types=1);

namespace CMS\Application\FormData\Handler\Page\Page;

use CMS\Application\Interface\CrudHandlerInterface;
use RuntimeException;

final class PageHandler implements CrudHandlerInterface
{
    public function __construct(
        private \mysqli $db
    ) {
    }

    public function load(string $id): array
    {
        $stmt = $this->db->prepare("
            SELECT
                page_uuid,
                slug,
                name,
                required_permission_id,
                page_css_id,
                fk_translation_placeholder,
                template,
                meta_title,
                meta_description,
                enabled,
                sort_order,
                created_at,
                updated_at,
                context,
                nav_id,
                area,
                auth_visibility
            FROM page
            WHERE page_uuid = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'Page load prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param('s', $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $page = $result ? $result->fetch_assoc() : [];

        $stmt->close();

        return $page ?: [];
    }

    public function save(array $data): string
    {
        $pageUuid = $this->uuid();

        $slug = $this->requiredString($data, 'slug');
        $name = $this->requiredString($data, 'name');

        $requiredPermissionId = $this->nullableString($data['required_permission_id'] ?? null);
        $pageCssId = $this->nullableString($data['page_css_id'] ?? null);
        $translationPlaceholder = $this->nullableString(
            $data['fk_translation_placeholder'] ?? null
        );

        $template = $this->nullableString($data['template'] ?? null) ?? 'default';
        $metaTitle = $this->nullableString($data['meta_title'] ?? null);
        $metaDescription = $this->nullableString($data['meta_description'] ?? null);

        $enabled = !empty($data['enabled']) ? 1 : 0;
        $sortOrder = (int)($data['sort_order'] ?? 0);

        $context = $this->nullableString($data['context'] ?? null) ?? 'frontend';
        $navId = $this->nullableString($data['nav_id'] ?? null) ?? 'generalNav';
        $area = $this->nullableString($data['area'] ?? null);

        $authVisibility = $this->authVisibility(
            $data['auth_visibility'] ?? 'public'
        );

        $this->assertSlugAvailable($slug);

        $stmt = $this->db->prepare("
            INSERT INTO page
            (
                page_uuid,
                slug,
                name,
                required_permission_id,
                page_css_id,
                fk_translation_placeholder,
                template,
                meta_title,
                meta_description,
                enabled,
                sort_order,
                context,
                nav_id,
                area,
                auth_visibility
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'Page save prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param(
'sssssssssiissss',
            $pageUuid,
            $slug,
            $name,
            $requiredPermissionId,
            $pageCssId,
            $translationPlaceholder,
            $template,
            $metaTitle,
            $metaDescription,
            $enabled,
            $sortOrder,
            $context,
            $navId,
            $area,
            $authVisibility
        );

        if (!$stmt->execute()) {
            throw new RuntimeException(
                'Seite konnte nicht gespeichert werden: ' . $stmt->error
            );
        }

        $stmt->close();

        return $pageUuid;
    }

    public function update(string $id, array $data): bool
    {
        $slug = $this->requiredString($data, 'slug');
        $name = $this->requiredString($data, 'name');

        $requiredPermissionId = $this->nullableString($data['required_permission_id'] ?? null);
        $pageCssId = $this->nullableString($data['page_css_id'] ?? null);
        $translationPlaceholder = $this->nullableString(
            $data['fk_translation_placeholder'] ?? null
        );

        $template = $this->nullableString($data['template'] ?? null) ?? 'default';
        $metaTitle = $this->nullableString($data['meta_title'] ?? null);
        $metaDescription = $this->nullableString($data['meta_description'] ?? null);

        $enabled = !empty($data['enabled']) ? 1 : 0;
        $sortOrder = (int)($data['sort_order'] ?? 0);

        $context = $this->nullableString($data['context'] ?? null) ?? 'frontend';
        $navId = $this->nullableString($data['nav_id'] ?? null) ?? 'generalNav';
        $area = $this->nullableString($data['area'] ?? null);

        $authVisibility = $this->authVisibility(
            $data['auth_visibility'] ?? 'public'
        );

        $this->assertSlugAvailable($slug, $id);

        $stmt = $this->db->prepare("
            UPDATE page
            SET
                slug = ?,
                name = ?,
                required_permission_id = ?,
                page_css_id = ?,
                fk_translation_placeholder = ?,
                template = ?,
                meta_title = ?,
                meta_description = ?,
                enabled = ?,
                sort_order = ?,
                context = ?,
                nav_id = ?,
                area = ?,
                auth_visibility = ?
            WHERE page_uuid = ?
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'Page update prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param(
            'ssssssssisssssss',
            $slug,
            $name,
            $requiredPermissionId,
            $pageCssId,
            $translationPlaceholder,
            $template,
            $metaTitle,
            $metaDescription,
            $enabled,
            $sortOrder,
            $context,
            $navId,
            $area,
            $authVisibility,
            $id
        );

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM page
            WHERE page_uuid = ?
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'Page delete prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param('s', $id);

        $success = $stmt->execute();
        $stmt->close();

        return $success;
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

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function authVisibility(mixed $value): string
    {
        $value = trim((string)$value);

        $allowed = [
            'public',
            'guest',
            'logged_in',
        ];

        if (!in_array($value, $allowed, true)) {
            throw new RuntimeException(
                'Ungültiger Wert für auth_visibility: ' . $value
            );
        }

        return $value;
    }

    private function assertSlugAvailable(string $slug, ?string $currentPageUuid = null): void
    {
        if ($currentPageUuid === null) {
            $stmt = $this->db->prepare("
                SELECT page_uuid
                FROM page
                WHERE slug = ?
                LIMIT 1
            ");

            if (!$stmt) {
                throw new RuntimeException(
                    'Slug-Prüfung prepare fehlgeschlagen: ' . $this->db->error
                );
            }

            $stmt->bind_param('s', $slug);
        } else {
            $stmt = $this->db->prepare("
                SELECT page_uuid
                FROM page
                WHERE slug = ?
                  AND page_uuid != ?
                LIMIT 1
            ");

            if (!$stmt) {
                throw new RuntimeException(
                    'Slug-Prüfung prepare fehlgeschlagen: ' . $this->db->error
                );
            }

            $stmt->bind_param('ss', $slug, $currentPageUuid);
        }

        $stmt->execute();

        $result = $stmt->get_result();
        $exists = $result && $result->fetch_assoc();

        $stmt->close();

        if ($exists) {
            throw new RuntimeException(
                'Der Slug "' . $slug . '" wird bereits von einer anderen Seite verwendet.'
            );
        }
    }

    private function uuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}