<?php
declare(strict_types=1);
 
namespace CMS\Application\FormData;

use CMS\Application\Interface\DataFormLoaderInterface;
use CMS\Application\FormData\EntityFormDataLoader;
use CMS\Application\FormData\TranslationFormDataLoader;
use CMS\Core\CMSApp;

final class DataFormLoaderResolver
{
 
    /** @var DataFormLoaderInterface[] */
    private static array $loaders = [];
 
    public static function register(DataFormLoaderInterface $loader): void
    {

        self::$loaders[] = $loader;
    }

    public static function resolve(array $block): ?DataFormLoaderInterface
    {
        // Auto-register default loaders if none registered
        if (empty(self::$loaders)) {
            $db = CMSApp::getDb();

            self::register(new TranslationFormDataLoader($db));
            self::register(new EntityFormDataLoader());
        }
        foreach (self::$loaders as $loader) {
            if ($loader->supports($block)) {
                return $loader;
            }
        }

        return null;
    }
}
