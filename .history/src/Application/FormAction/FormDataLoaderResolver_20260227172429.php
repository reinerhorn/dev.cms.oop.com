<?php
declare(strict_types=1);
 
namespace CMS\Application\FormAction;

use CMS\Application\FormData\FormDataLoaderInterface;
use CMS\Application\FormData\EntityFormDataLoader;
use CMS\Application\FormData\TranslationFormDataLoader;
use CMS\Core\CMSApp;

final class FormDataLoaderResolver
{
 
    /** @var FormDataLoaderInterface[] */
    private static array $loaders = [];
 
    public static function register(FormDataLoaderInterface $loader): void
    {

        self::$loaders[] = $loader;
    }

    public static function resolve(string $formAction): ?FormDataLoaderInterface
    {
        // Auto-register default loaders if none registered
        if (empty(self::$loaders)) {
            $db = CMSApp::getDb();

            self::register(new TranslationFormDataLoader($db));
            self::register(new EntityFormDataLoader($db));
        }
        //var_dump($formAction);
        //die;
        foreach (self::$loaders as $loader) {
            if ($loader->supports($formAction)) {
                return $loader;
            }
        }

        return null;
    }
}
