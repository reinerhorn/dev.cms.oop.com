<?php
namespace CMS\Service;

final class AssetResolver
{
    public function resolve(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'svg'  => $path,
            'webp' => $path,
            'png'  => $path,
            'jpg'  => $path,
            default => $path
        };
    }
}
