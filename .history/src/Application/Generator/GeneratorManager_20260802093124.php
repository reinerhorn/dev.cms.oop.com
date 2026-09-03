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

        if (!empty($config['generate_form'])) {
            $jsonGenerator = new JsonFormGenerator($this->db);
            $results['form'] = $jsonGenerator->generate($config);
        }

        if (!empty($config['generate_handler'])) {
            $crudGenerator = new CrudHandlerGenerator($this->db);
            $results['handler'] = $crudGenerator->generate($config);
        }

        if (!empty($config['register_plugin'])) {
            $pluginGenerator = new PluginRegistrationGenerator($this->db);
            $results['plugin'] = $pluginGenerator->generate($config);
        }

        return [
            'success' => true,
            'results' => $results,
        ];
    }
}
