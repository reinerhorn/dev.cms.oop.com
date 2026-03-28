<?php

namespace CMS\Controller\Admin;

use CMS\Core\Generator\FormGenerator;
use Twig\Environment;
use mysqli;

class GeneratorController
{
    private mysqli $db;
    private Environment $twig;

    public function __construct(mysqli $db, Environment $twig)
    {
        $this->db = $db;
        $this->twig = $twig;
    }

    public function index(): void
    {
        $generator = new FormGenerator($this->db);

        $tables = $generator->getTables();

        echo $this->twig->render('admin/generator.twig', [
            'tables' => $tables
        ]);
    }

    public function create(): void
    {
        $table = $_POST['table'] ?? null;

        if (!$table) {
            echo "Keine Tabelle angegeben";
            return;
        }

        $generator = new FormGenerator($this->db);

        $generator->createEditor($table);

        header("Location: /admin/generator");
    }
}
