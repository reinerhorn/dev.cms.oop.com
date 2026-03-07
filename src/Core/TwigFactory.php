<?php
namespace App\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;

class TwigFactory {
    public static function create(): Environment {
        $env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'development';
        $isDebug = ($env !== 'production');
        $loader = new FilesystemLoader(__DIR__ . '/../../templates');
        $twig = new Environment($loader, [
            'cache' => $isDebug ? false : __DIR__ . '/../../var/cache/twig',
            'debug' => $isDebug
        ]);
        if ($isDebug) {
            $twig->addExtension(new DebugExtension());
        }
        return $twig;
    }
}
