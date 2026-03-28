<?php
declare(strict_types=1);

namespace CMS\Application\FormData;

use CMS\Core\CMSApp;
use CMS\Application\FormAction\Admin\EntityFormDataLoader;

final class FormDataLoaderCollector
{
    public static function collect(): array
    {
        return [
            new EntityFormDataLoader(),
        ];
    }
}
