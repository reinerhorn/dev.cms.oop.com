    {
        // 🔥 Generator direkt ausführen (kein DB-Load notwendig)
        $generator = new \CMS\Core\Generator\FormGenerator($db);
        $tables = $generator->getTables();

        return [
            'type'   => 'generator',
            'tables' => $tables
        ];
    }
