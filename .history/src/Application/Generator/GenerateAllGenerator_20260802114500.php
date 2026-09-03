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
        $config['generate_form'] = true;
        $config['generate_handler'] = true;
        $config['generate_repository'] = true;
        $config['generate_service'] = true;
        $config['generate_controller'] = true;
        $config['register_plugin'] = true;
        $config['generate_all'] = true;

        $manager = new GeneratorManager($this->db);

        $result = $manager->generate($config);

        $result['button'] = [
            'button_key'   => $config['button_key']   ?? '',
            'button_label' => $config['button_label'] ?? '',
            'action'       => $config['action']       ?? '',
        ];

        return $result;
    }
}
