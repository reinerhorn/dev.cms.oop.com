<?php
namespace CMS\Application\Generator;


$generator = new GeneratorManager($this->db);

$generator->generate([
    'tables'   => $tables,
    'form_key' => $saveKey,
    'module'   => $module
]);