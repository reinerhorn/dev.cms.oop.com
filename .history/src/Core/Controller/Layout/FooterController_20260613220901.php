    /**
     * Normalisiert Footer-Images (CMS-sichere Datenbasis)
     */
    private static function normalizeImages(array $images): array
    {
        $result = [];

        foreach ($images as $img) {

            $imageUrl = trim($img['image_url'] ?? '');
            $linkUrl  = trim($img['link_url'] ?? '');
            $alt      = trim($img['alt_text'] ?? '');

            // Skip broken entries
            if ($imageUrl === '') {
                continue;
            }

            // detect file type (svg, webp, png, jpg, etc.)
            $ext = strtolower(pathinfo($imageUrl, PATHINFO_EXTENSION));

            $type = match ($ext) {
                'svg'  => 'svg',
                'webp' => 'image',
                'png'  => 'image',
                'jpg'  => 'image',
                'jpeg' => 'image',
                default => 'image'
            };

            $result[] = [
                'image_url' => $imageUrl,
                'link_url'  => $linkUrl,
                'alt_text'  => $alt,
                'type'      => $type
            ];
        }

        return $result;
    }