<?php

namespace CMS\Application\Service;

final class AssetResolver
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? ($_SERVER['DOCUMENT_ROOT'] ?? '');
    }

    public function resolve(string $path): string
    {
        $path = $this->sanitize($path);

        if ($path === '') {
            return '';
        }

        $path = '/' . ltrim($path, '/');

        return $path;
    }

    public function isSvg(string $path): bool
    {
        $path = strtolower($path);
        return pathinfo($path, PATHINFO_EXTENSION) === 'svg';
    }

    public function loadSvg(string $path): string
    {
        $cleanPath = $this->resolve($path);

        $fullPath = $this->basePath . '/' . ltrim($cleanPath, '/');

        if (!is_file($fullPath)) {
            return '';
        }

        return file_get_contents($fullPath);
    }

    public function exists(string $path): bool
    {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');
        return is_file($fullPath);
    }

    public function debug(string $path): array
    {
        $clean = $this->resolve($path);

        return [
            'original' => $path,
            'resolved' => $clean,
            'exists'   => $this->exists($clean),
            'ext'      => strtolower(pathinfo($clean, PATHINFO_EXTENSION)),
            'basePath' => $this->basePath,
        ];
    }

    private function sanitize(string $path): string
    {
        $path = trim($path);

        // remove invisible characters / line breaks
        $path = preg_replace('/\s+/', '', $path);

        // normalize slashes
        $path = str_replace('\\', '/', $path);

        return $path;
    }
}
