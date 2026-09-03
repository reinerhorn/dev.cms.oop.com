<?php

declare(strict_types=1);

namespace CMS\Application\Generator;

use mysqli;

final class GenerateAllGenerator
{
    public function __construct(
        private mysqli $db
    ) {}

    public function generate(array $config): array
    {
        $flags = [
            'generate_form',
            'generate_handler',
            'generate_repository',
            'generate_service',
            'generate_controller',
            'register_plugin',
        ];

        foreach ($flags as $flag) {
            $config[$flag] = true;
        }

        $config['generate_all'] = true;

        $manager = new GeneratorManager($this->db);
        $result = $manager->generate($config);

        $result['generator'] = [
            'entity'      => $config['entity'] ?? '',
            'table'       => $config['table'] ?? '',
            'plugin_key'  => $config['plugin_key'] ?? '',
            'module'      => $config['module'] ?? '',
            'button_key'  => $config['button_key'] ?? '',
            'action'      => $config['action'] ?? '',
            'generatedAt' => date('Y-m-d H:i:s'),
        ];

        return $result;
    }
}
