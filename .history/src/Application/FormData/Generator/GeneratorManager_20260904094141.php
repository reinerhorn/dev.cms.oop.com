<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class GeneratorManager
{
    public function __construct(
        private mysqli $db
    ) {
    }

    /**
     * Führt alle Generatoren aus.
     *
     * Reihenfolge:
     *
     * 1. Page erzeugen oder vorhandene Page verwenden
     * 2. Navigation erzeugen
     * 3. JSON-Formular erzeugen
     * 4. CRUD Handler erzeugen
     * 5. Repository erzeugen
     * 6. Service erzeugen
     * 7. Controller erzeugen
     * 8. Plugin erzeugen und registrieren
     */
    public function generate(array $config): array
    {
        $config = $this->normalizeConfig($config);

        $this->validateConfig($config);

        $results = [];

        /*
         * =========================================================
         * 1. PAGE
         * =========================================================
         *
         * own_page = yes
         *     → Neue Page erzeugen
         *
         * own_page = no
         *     → Vorhandene Page verwenden
         */
        if ($config['own_page'] === 'yes') {
            $generator = new PageGenerator($this->db);

            $page = $generator->generate($config);

            $results['page'] = $page;

            /*
             * Die neue Page wird sofort für alle
             * folgenden Generatoren übernommen.
             */
            $config['page_uuid'] = $page['page_uuid'];
            $config['page'] = $page['page_uuid'];
            $config['page_slug'] = $page['slug'];

            error_log(
                'PAGE GENERIERT: ' .
                print_r($page, true)
            );
        } else {
            /*
             * Vorhandene Page laden.
             */
            $page = $this->loadExistingPage(
                $config['page']
            );

            $results['page'] = $page;

            /*
             * Einheitliche Werte für die
             * folgenden Generatoren.
             */
            $config['page_uuid'] =
                $page['page_uuid'];

            $config['page_slug'] =
                $page['slug'];

            error_log(
                'BESTEHENDE PAGE VERWENDET: ' .
                print_r($page, true)
            );
        }

        /*
         * =========================================================
         * 2. NAVIGATION
         * =========================================================
         *
         * Optional:
         *
         * create_navigation = yes
         *
         * Kein Parent:
         *
         * main
         *
         * Mit Parent:
         *
         * sub
         *
         * Beliebig tiefe Navigation wird über
         * navigation_parent_id ermöglicht.
         */
        if ($config['create_navigation']) {
            $generator = new NavigationGenerator($this->db);

            $navigation = $generator->generate($config);

            $results['navigation'] = $navigation;

            /*
             * Navigation UUID ebenfalls verfügbar
             * machen, falls sie später benötigt wird.
             */
            $config['navigation_uuid'] =
                $navigation['nav_uuid'];

            error_log(
                'NAVIGATION GENERIERT: ' .
                print_r($navigation, true)
            );
        }

        /*
         * =========================================================
         * 3. JSON FORMULAR
         * =========================================================
         */
        if ($config['generate_json_form']) {
            $generator = new JsonFormGenerator($this->db);

            $results['json_form'] =
                $generator->generate($config);
        }

        /*
         * =========================================================
         * 4. CRUD HANDLER
         * =========================================================
         */
        if ($config['generate_crud']) {
            $generator = new CrudHandlerGenerator($this->db);

            $results['crud'] =
                $generator->generate($config);
        }

        /*
         * =========================================================
         * 5. REPOSITORY
         * =========================================================
         */
        if ($config['generate_repository']) {
            $generator = new RepositoryGenerator($this->db);

            $results['repository'] =
                $generator->generate($config);
        }

        /*
         * =========================================================
         * 6. SERVICE
         * =========================================================
         */
        if ($config['generate_service']) {
            $generator = new ServiceGenerator($this->db);

            $results['service'] =
                $generator->generate($config);
        }

        /*
         * =========================================================
         * 7. CONTROLLER
         * =========================================================
         */
        if ($config['generate_controller']) {
            $generator = new ControllerGenerator($this->db);

            $results['controller'] =
                $generator->generate($config);
        }

        /*
         * =========================================================
         * 8. PLUGIN ERZEUGEN + REGISTRIEREN
         * =========================================================
         */
        if ($config['generate_plugin']) {
            $pluginGenerator =
                new PluginRegistrationGenerator(
                    $this->db
                );

            /*
             * Plugin-Konfiguration erzeugen.
             */
            $plugin =
                $pluginGenerator->generate($config);

            /*
             * Plugin tatsächlich registrieren.
             */
            $pluginGenerator->register($plugin);

            $results['plugin'] = $plugin;

            error_log(
                'PLUGIN GENERIERT UND REGISTRIERT: ' .
                print_r($plugin, true)
            );
        }

        /*
         * =========================================================
         * SUMMARY
         * =========================================================
         */
        return [
            'success' => true,

            'generated' => count($results),

            'timestamp' =>
                date('Y-m-d H:i:s'),

            'config' => [
                'table' => $config['table'],
                'form_type' => $config['form_type'],
                'save_key' => $config['save_key'],

                'own_page' => $config['own_page'],

                'page_uuid' =>
                    $config['page_uuid'],

                'page_slug' =>
                    $config['page_slug'],

                'create_navigation' =>
                    $config['create_navigation'],

                'navigation_parent_id' =>
                    $config['navigation_parent_id'],

                'navigation_context_id' =>
                    $config['navigation_context_id'],

                'slug_mode' =>
                    $config['slug_mode'],

                'slug' =>
                    $config['slug'],

                'new_slug' =>
                    $config['new_slug'],
            ],

            'results' => $results,
        ];
    }

    /**
     * Lädt eine vorhandene Page.
     *
     * Es wird primär über page_uuid gesucht.
     * Falls keine UUID gefunden wird, wird
     * zusätzlich über slug gesucht.
     */
    private function loadExistingPage(
        string $pageIdentifier
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                page_uuid,
                slug,
                name,
                required_permission_id,
                page_css_id,
                fk_translation_placeholder,
                template,
                enabled,
                sort_order
            FROM page
            WHERE page_uuid = ?
               OR slug = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'Bestehende Page prepare fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            'ss',
            $pageIdentifier,
            $pageIdentifier
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'Bestehende Page konnte nicht geladen werden: '
                . $error
            );
        }

        $result = $stmt->get_result();

        $page = $result
            ? $result->fetch_assoc()
            : null;

        $stmt->close();

        if (!$page) {
            throw new RuntimeException(
                'Page wurde nicht gefunden: '
                . $pageIdentifier
            );
        }

        return $page;
    }

    /**
     * Normalisiert die Generator-Konfiguration.
     */
    private function normalizeConfig(
        array $config
    ): array {
        $defaults = [

            /*
             * -----------------------------------------------------
             * DATABASE
             * -----------------------------------------------------
             */
            'table' => '',
            'db_table' => '',

            /*
             * -----------------------------------------------------
             * FORM
             * -----------------------------------------------------
             */
            'form_type' => 'entry',
            'save_key' => '',

            /*
             * -----------------------------------------------------
             * PAGE
             * -----------------------------------------------------
             */
            'own_page' => 'no',
            'page' => '',
            'page_uuid' => '',
            'page_slug' => '',

            /*
             * -----------------------------------------------------
             * SLUG
             * -----------------------------------------------------
             */
            'slug_mode' => 'existing',
            'slug' => '',
            'new_slug' => '',

            /*
             * -----------------------------------------------------
             * NAVIGATION
             * -----------------------------------------------------
             */
            'create_navigation' => false,

            'navigation_parent_id' => '',

            'navigation_title' => '',

            'navigation_slug' => '',

            'navigation_translation_placeholder' =>
                '',

            'navigation_sort_order' => null,

            'navigation_enabled' => true,

            'navigation_align' => 'left',

            'navigation_context_id' => 'admin',

            'navigation_permission_id' =>
                'perm-view-admin',

            'navigation_auth_visibility' =>
                'public',

            /*
             * -----------------------------------------------------
             * GENERATOR
             * -----------------------------------------------------
             */
            'multi_table' => false,

            'entity' => '',

            'module' => '',

            'namespace' => '',

            'output_path' => '',

            /*
             * -----------------------------------------------------
             * GENERATOR FLAGS
             * -----------------------------------------------------
             */
            'generate_json_form' => true,

            'generate_crud' => true,

            'generate_repository' => true,

            'generate_service' => true,

            'generate_controller' => true,

            'generate_plugin' => true,

            /*
             * -----------------------------------------------------
             * FORM / DISPATCHER
             * -----------------------------------------------------
             */
            'action' => '',

            'form_id' => '',
        ];

        $config = array_merge(
            $defaults,
            $config
        );

        /*
         * db_table ist Alias für table.
         */
        if (
            $config['table'] === ''
            && $config['db_table'] !== ''
        ) {
            $config['table'] =
                $config['db_table'];
        }

        if (
            $config['db_table'] === ''
            && $config['table'] !== ''
        ) {
            $config['db_table'] =
                $config['table'];
        }

        /*
         * Boolean-Werte.
         */
        $booleanFields = [
            'multi_table',

            'create_navigation',

            'navigation_enabled',

            'generate_json_form',

            'generate_crud',

            'generate_repository',

            'generate_service',

            'generate_controller',

            'generate_plugin',
        ];

        foreach ($booleanFields as $field) {
            $config[$field] =
                $this->toBool(
                    $config[$field]
                );
        }

        /*
         * Strings normalisieren.
         */
        $stringFields = [

            /*
             * Database
             */
            'table',
            'db_table',

            /*
             * Form
             */
            'form_type',
            'save_key',

            /*
             * Page
             */
            'own_page',
            'page',
            'page_uuid',
            'page_slug',

            /*
             * Slug
             */
            'slug_mode',
            'slug',
            'new_slug',

            /*
             * Navigation
             */
            'navigation_parent_id',

            'navigation_title',

            'navigation_slug',

            'navigation_translation_placeholder',

            'navigation_align',

            'navigation_context_id',

            'navigation_permission_id',

            'navigation_auth_visibility',

            /*
             * Generator
             */
            'entity',
            'module',
            'namespace',
            'output_path',

            /*
             * Form
             */
            'action',
            'form_id',
        ];

        foreach ($stringFields as $field) {
            if (isset($config[$field])) {
                $config[$field] = trim(
                    (string) $config[$field]
                );
            }
        }

        /*
         * Sort Order.
         */
        if (
            $config['navigation_sort_order'] === ''
            || $config['navigation_sort_order'] === null
        ) {
            $config['navigation_sort_order'] = null;
        } else {
            $config['navigation_sort_order'] =
                (int) $config[
                    'navigation_sort_order'
                ];
        }

        return $config;
    }

    /**
     * Validiert die Generator-Konfiguration.
     */
    private function validateConfig(
        array $config
    ): void {
        /*
         * ---------------------------------------------------------
         * TABLE
         * ---------------------------------------------------------
         */
        if ($config['table'] === '') {
            throw new RuntimeException(
                'Keine Datenbanktabelle angegeben.'
            );
        }

        if (
            !preg_match(
                '/^[a-zA-Z0-9_]+$/',
                $config['table']
            )
        ) {
            throw new RuntimeException(
                'Ungültiger Tabellenname: '
                . $config['table']
            );
        }

        /*
         * ---------------------------------------------------------
         * SAVE KEY
         * ---------------------------------------------------------
         */
        if ($config['save_key'] === '') {
            throw new RuntimeException(
                'Kein Form Key angegeben.'
            );
        }

        if (
            !preg_match(
                '/^[a-zA-Z0-9_-]+$/',
                $config['save_key']
            )
        ) {
            throw new RuntimeException(
                'Ungültiger Form Key: '
                . $config['save_key']
            );
        }

        /*
         * ---------------------------------------------------------
         * FORM TYPE
         * ---------------------------------------------------------
         */
        if (
            !in_array(
                $config['form_type'],
                [
                    'entry',
                    'simple',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Ungültiger Formulartyp: '
                . $config['form_type']
            );
        }

        /*
         * ---------------------------------------------------------
         * OWN PAGE
         * ---------------------------------------------------------
         */
        if (
            !in_array(
                $config['own_page'],
                [
                    'yes',
                    'no',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Ungültiger Wert für own_page: '
                . $config['own_page']
            );
        }

        /*
         * ---------------------------------------------------------
         * EXISTING PAGE
         * ---------------------------------------------------------
         */
        if (
            $config['own_page'] === 'no'
            && $config['page'] === ''
        ) {
            throw new RuntimeException(
                'Bei own_page=no muss eine vorhandene '
                . 'Page ausgewählt werden.'
            );
        }

        /*
         * ---------------------------------------------------------
         * NEW PAGE
         * ---------------------------------------------------------
         */
        if ($config['own_page'] === 'yes') {
            if (
                !in_array(
                    $config['slug_mode'],
                    [
                        'existing',
                        'new',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Ungültiger slug_mode: '
                    . $config['slug_mode']
                );
            }

            /*
             * Existing slug.
             */
            if (
                $config['slug_mode'] === 'existing'
                && $config['slug'] === ''
            ) {
                throw new RuntimeException(
                    'Bei slug_mode=existing muss ein '
                    . 'Slug ausgewählt werden.'
                );
            }

            /*
             * New slug.
             */
            if (
                $config['slug_mode'] === 'new'
                && $config['new_slug'] === ''
            ) {
                throw new RuntimeException(
                    'Bei slug_mode=new muss ein neuer '
                    . 'Slug angegeben werden.'
                );
            }

            if (
                $config['slug_mode'] === 'new'
                && !preg_match(
                    '/^[a-zA-Z0-9_-]+$/',
                    $config['new_slug']
                )
            ) {
                throw new RuntimeException(
                    'Ungültiger neuer Slug: '
                    . $config['new_slug']
                );
            }
        }

        /*
         * ---------------------------------------------------------
         * NAVIGATION
         * ---------------------------------------------------------
         */
        if ($config['create_navigation']) {
            /*
             * Context ist Pflicht.
             */
            if (
                $config['navigation_context_id'] === ''
            ) {
                throw new RuntimeException(
                    'Navigation Context darf nicht '
                    . 'leer sein.'
                );
            }
        }
    }

    /**
     * Konvertiert unterschiedliche Werte in Boolean.
     */
    private function toBool(
        mixed $value
    ): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return in_array(
                strtolower(
                    trim($value)
                ),
                [
                    '1',
                    'true',
                    'yes',
                    'ja',
                    'on',
                ],
                true
            );
        }

        return !empty($value);
    }
}

