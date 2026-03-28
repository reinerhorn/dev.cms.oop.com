<?php
declare(strict_types=1);

namespace CMS\Application\FormAction;

use CMS\Application\FormData\FormDataLoaderInterface;

final class FormDataLoaderResolver
{
    /** @var FormDataLoaderInterface[] */
    private static array $loaders = [];

    /**
     * Bootstraps the resolver with all available loaders (called once in CMSApp::init)
     */
    public static function boot(array $loaders): void
    {
        self::$loaders = $loaders;
    }

    /**
     * Resolves the appropriate loader for a given form action
     */
    public static function resolve(string $formAction): ?FormDataLoaderInterface
    {
        foreach (self::$loaders as $loader) {
            if ($loader->supports($formAction)) {
                return $loader;
            }
        }

        return null;
    }
}
