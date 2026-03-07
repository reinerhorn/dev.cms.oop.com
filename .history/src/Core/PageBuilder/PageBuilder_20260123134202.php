<?php

namespace CMS\Core\PageBuilder;

use mysqli;

class PageBuilder
{
    private mysqli $db;
    private string $language;
    private string $role;

    public function __construct(mysqli $db, string $language, string $role)
    {
        $this->db = $db;
        $this->language = $language;
        $this->role = $role;
    }

    public function build(string $pageId): array
    {
        // LEGACY STUB
        // PageBuilder is deprecated and no longer responsible for
        // layout, navigation, permissions or content loading.

        return [
            'meta' => [
                'language' => $this->language,
                'role'     => $this->role,
                'page_id'  => $pageId,
            ],
            'header'            => [],
            'navigation'        => [],
            'content_template'  => null,
            'content_data'      => [],
            'footer'            => [],
        ];
    }
}
