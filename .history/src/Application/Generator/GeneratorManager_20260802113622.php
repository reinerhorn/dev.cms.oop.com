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
        $config = array_merge([
            'entity'      => '',
            'table'       => '',
            'namespace'   => '',
            'module'      => '',
            'plugin_key'  => '',
            'output_path' => '',
        ], $config);

        if (!empty($config['generate_form'])) {
            $jsonGenerator = new JsonFormGenerator($this->db);
            $results['form'] = $jsonGenerator->generate($config);
        }

        if (!empty($config['generate_handler'])) {
            $crudGenerator = new CrudHandlerGenerator($this->db);
            $results['handler'] = $crudGenerator->generate($config);
        }

        if (!empty($config['generate_repository'])) {
            $repositoryGenerator = new RepositoryGenerator($this->db);
            $results['repository'] = $repositoryGenerator->generate($config);
        }

        if (!empty($config['generate_service'])) {
            $serviceGenerator = new ServiceGenerator($this->db);
            $results['service'] = $serviceGenerator->generate($config);
        }

        if (!empty($config['generate_controller'])) {
            $controllerGenerator = new ControllerGenerator($this->db);
            $results['controller'] = $controllerGenerator->generate($config);
        }

        if (!empty($config['register_plugin'])) {
            $pluginGenerator = new PluginRegistrationGenerator($this->db);
            $results['plugin'] = $pluginGenerator->generate($config);
        }

        return [
            'success'   => true,
            'generated' => count($results),
            'timestamp' => date('Y-m-d H:i:s'),
            'config'    => $config,
            'results'   => $results,
        ];
    }
}
