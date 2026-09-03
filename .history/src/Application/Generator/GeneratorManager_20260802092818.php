<?php

declare(strict_types=1);

namespace CMS\Application\Generator;

use mysqli;

final class GeneratorManager
{
    public function __construct(
        private mysqli $db
    ) {
    }

    public function generate(array $config): array
    {
        $results = [];

        // 1. Formular erzeugen
        $jsonGenerator = new JsonFormGenerator($this->db);
        $results['form'] = $jsonGenerator->generate($config);

        // 2. CRUD-Handler erzeugen
        $crudGenerator = new CrudHandlerGenerator($this->db);
        $results['handler'] = $crudGenerator->generate($config);

        // 3. Plugin registrieren
        $pluginGenerator = new PluginRegistrationGenerator($this->db);
        $results['plugin'] = $pluginGenerator->generate($config);

        return [
            'success' => true,
            'results' => $results,
        ];
    }
}
