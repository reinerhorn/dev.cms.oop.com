<?php
declare(strict_types=1);
 
namespace CMS\Application\FormAction;

use CMS\Application\FormData\FormDataLoaderInterface;

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
        var_dump($formAction);
die;
        foreach (self::$loaders as $loader) {
            if ($loader->supports($formAction)) {
                return $loader;
            }
        }

        return null;
    }
}
