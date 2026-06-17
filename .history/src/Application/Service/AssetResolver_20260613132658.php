<?php
namespace CMS\Application\Service;

final class AssetResolver
{
    public function resolve(string $path): string
    {
        $path = trim($path);
        $path = '/' . ltrim($path, '/');
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'svg',
            'webp',
            'png',
            'jpg',
            'jpeg' => $path,
            default => $path,
        };
    }
}
