<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;
use Throwable;

final class GenerateAllGenerator
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Führt den kompletten Generator aus.
     *
     * @param array<string,mixed> $postData
     *
     * @return array<string,mixed>
     */
    public function handle(array $postData): array
    {
        try {
            error_log(

                'GENERATOR RAW POST: '

                    . print_r($postData, true)

            );
            $config = $this->buildConfig($postData);

            $this->validateConfig($config);

            $manager = new GeneratorManager($this->db);

            if (!method_exists($manager, 'generate')) {
                throw new RuntimeException(
                    'GeneratorManager::generate() wurde nicht gefunden.'
                );
            }

            $results = $manager->generate($config);

            return [
                'success' => true,

                'message' =>
                'Generator erfolgreich ausgeführt.',

                'config' =>
                $config,

                'results' =>
                $results,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,

                'message' =>
                $e->getMessage(),

                'error' => [
                    'class' =>
                    $e::class,

                    'code' =>
                    $e->getCode(),
                ],
            ];
        }
    }

    /**
     * Baut die Generator-Konfiguration aus POST-Daten.
     *
     * @param array<string,mixed> $postData
     *
     * @return array<string,mixed>
     */
    private function buildConfig(array $postData): array
    {
        $data = $this->flattenPostData($postData);

        /*
         * ---------------------------------------------------------
         * Basis
         * ---------------------------------------------------------
         */

        $table =
            $this->stringValue(
                $data['table']
                    ?? $data['db_table']
                    ?? ''
            );

        $formType =
            $this->stringValue(
                $data['form_type']
                    ?? 'entry'
            );

        $saveKey =
            $this->stringValue(
                $data['save_key']
                    ?? $data['form_key']
                    ?? ''
            );

        /*
         * ---------------------------------------------------------
         * Page
         * ---------------------------------------------------------
         */

        $ownPage =
            $this->boolValue(
                $data['own_page']
                    ?? false
            );

        $page =
            $this->stringValue(
                $data['page']
                    ?? $data['page_uuid']
                    ?? ''
            );

        $slugMode =
            $this->stringValue(
                $data['slug_mode']
                    ?? 'new'
            );

        $slug =
            $this->stringValue(
                $data['slug']
                    ?? ''
            );

        $newSlug =
            $this->stringValue(
                $data['new_slug']
                    ?? ''
            );

        /*
         * Page-Kontext
         *
         * frontend
         * frontend-auth
         * backend
         */

        $context =
            $this->stringValue(
                $data['context']
                    ?? 'frontend'
            );

        /*
         * Navigation-ID der Page.
         *
         * Die Page-Navigation richtet sich nach dem Page-Kontext.
         * Die eigentliche Admin-/Member-Auswahl erfolgt später
         * über LayoutController anhand der Rolle.
         *
         * frontend       -> generalNav
         * frontend-auth  -> generalNav
         * backend        -> adminNav
         */

        $navId = match ($context) {
            'backend' => 'adminNav',
            'frontend',
            'frontend-auth' => 'generalNav',
            default => 'generalNav',
        };

        /*
         * Berechtigungen der Page.
         *
         * Beispiele:
         * perm-view-admin
         * perm-view-member
         */

        $requiredPermissionId =
            $this->nullableStringValue(
                $data['required_permission_id']
                    ?? null
            );

        /*
         * Sichtbarkeit:
         *
         * public
         * guest
         * logged_in
         */

        $authVisibility =
            $this->stringValue(
                $data['auth_visibility']
                    ?? 'public'
            );

        /*
         * CSS-Kontext der Page.
         *
         * Beispiele:
         * admin
         * member
         * form-box
         */

        $pageCssId =
            $this->nullableStringValue(
                $data['page_css_id']
                    ?? null
            );

        /*
         * Twig/Page-Template.
         */

        $template =
            $this->stringValue(
                $data['template']
                    ?? 'default'
            );

        /*
         * Meta-Daten.
         */

        $metaTitle =
            $this->nullableStringValue(
                $data['meta_title']
                    ?? null
            );

        $metaDescription =
            $this->nullableStringValue(
                $data['meta_description']
                    ?? null
            );

        /*
         * Page aktiv?
         */

        $enabled =
            $this->boolValue(
                $data['enabled']
                    ?? true
            );

        /*
         * Sortierung der Page.
         */

        $sortOrder =
            $this->nullableInt(
                $data['sort_order']
                    ?? 0
            );

        /*
         * form_action wird bewusst nur übernommen,
         * aber NICHT weiter verarbeitet.
         *
         * Die Spalte existiert noch im System.
         * Sie ist aktuell nicht Teil der Generator-Logik.
         */

        $formAction =
            $this->nullableStringValue(
                $data['form_action']
                    ?? null
            );

        /*
         * ---------------------------------------------------------
         * Navigation
         * ---------------------------------------------------------
         */

        $createNavigation =
            $this->boolValue(
                $data['create_navigation']
                    ?? false
            );

        $navigationParentId =
            $this->stringValue(
                $data['navigation_parent_id']
                    ?? ''
            );

        $navigationTitle =
            $this->stringValue(
                $data['navigation_title']
                    ?? ''
            );

        $navigationSlug =
            $this->stringValue(
                $data['navigation_slug']
                    ?? ''
            );

        $navigationTranslationPlaceholder =
            $this->stringValue(
                $data['navigation_translation_placeholder']
                    ?? ''
            );

        $navigationSortOrder =
            $this->nullableInt(
                $data['navigation_sort_order']
                    ?? null
            );

        $navigationEnabled =
            $this->boolValue(
                $data['navigation_enabled']
                    ?? true
            );

        $navigationAlign =
            $this->stringValue(
                $data['navigation_align']
                    ?? 'left'
            );

        $navigationContextId =
            $this->stringValue(
                $data['navigation_context_id']
                    ?? 'admin'
            );

        $navigationPermissionId =
            $this->nullableStringValue(
                $data['navigation_permission_id']
                    ?? null
            );

        $navigationAuthVisibility =
            $this->stringValue(
                $data['navigation_auth_visibility']
                    ?? 'public'
            );

        /*
         * ---------------------------------------------------------
         * Weitere Generatoroptionen
         * ---------------------------------------------------------
         */

        $multiTable =
            $this->boolValue(
                $data['multi_table']
                    ?? false
            );

        $entity =
            $this->stringValue(
                $data['entity']
                    ?? ''
            );

        $module =
            $this->stringValue(
                $data['module']
                    ?? ''
            );

        $namespace =
            $this->stringValue(
                $data['namespace']
                    ?? ''
            );

        $outputPath =
            $this->stringValue(
                $data['output_path']
                    ?? ''
            );

        /*
         * ---------------------------------------------------------
         * Generation Flags
         * ---------------------------------------------------------
         */

        $generateAll =
            $this->boolValue(
                $data['generate_all']
                    ?? true
            );

        $generateJsonForm =
            $this->boolValue(
                $data['generate_json_form']
                    ?? $generateAll
            );

        $generateCrud =
            $this->boolValue(
                $data['generate_crud']
                    ?? $generateAll
            );

        $generateRepository =
            $this->boolValue(
                $data['generate_repository']
                    ?? $generateAll
            );

        $generateService =
            $this->boolValue(
                $data['generate_service']
                    ?? $generateAll
            );

        $generateController =
            $this->boolValue(
                $data['generate_controller']
                    ?? $generateAll
            );

        /*
         * ---------------------------------------------------------
         * Ergebnis
         * ---------------------------------------------------------
         */

        return [
            'table' =>
            $table,

            'db_table' =>
            $table,

            'form_type' =>
            $formType,

            'save_key' =>
            $saveKey,

            /*
             * Page
             */

            'own_page' =>
            $ownPage,

            'page' =>
            $page,

            'page_uuid' =>
            '',

            'page_slug' =>
            '',

            'slug_mode' =>
            $slugMode,

            'slug' =>
            $slug,

            'new_slug' =>
            $newSlug,

            'context' =>
            $context,

            'nav_id' =>
            $navId,

            'required_permission_id' =>
            $requiredPermissionId,

            'page_css_id' =>
            $pageCssId,

            'auth_visibility' =>
            $authVisibility,

            'template' =>
            $template,

            'meta_title' =>
            $metaTitle,

            'meta_description' =>
            $metaDescription,

            'enabled' =>
            $enabled,

            'sort_order' =>
            $sortOrder,

            //'form_action' =>
            //   $formAction,

            /*
             * Navigation
             */

            'create_navigation' =>
            $createNavigation,

            'navigation_parent_id' =>
            $navigationParentId,

            'navigation_title' =>
            $navigationTitle,

            'navigation_slug' =>
            $navigationSlug,

            'navigation_translation_placeholder' =>
            $navigationTranslationPlaceholder,

            'navigation_sort_order' =>
            $navigationSortOrder,

            'navigation_enabled' =>
            $navigationEnabled,

            'navigation_align' =>
            $navigationAlign,

            'navigation_context_id' =>
            $navigationContextId,

            'navigation_permission_id' =>
            $navigationPermissionId,

            'navigation_auth_visibility' =>
            $navigationAuthVisibility,

            /*
             * Weitere Generatoroptionen
             */

            'multi_table' =>
            $multiTable,

            'entity' =>
            $entity,

            'module' =>
            $module,

            'namespace' =>
            $namespace,

            'output_path' =>
            $outputPath,

            /*
             * Generation Flags
             */

            'generate_all' =>
            $generateAll,

            'generate_json_form' =>
            $generateJsonForm,

            'generate_crud' =>
            $generateCrud,

            'generate_repository' =>
            $generateRepository,

            'generate_service' =>
            $generateService,

            'generate_controller' =>
            $generateController,
        ];
    }

    /**
     * Validiert die Generator-Konfiguration.
     *
     * @param array<string,mixed> $config
     */
    private function validateConfig(array $config): void
    {
        /*
         * ---------------------------------------------------------
         * Basis
         * ---------------------------------------------------------
         */

        if ($config['table'] === '') {
            throw new RuntimeException(
                'Es wurde keine Datenbanktabelle angegeben.'
            );
        }

        if ($config['form_type'] === '') {
            throw new RuntimeException(
                'Es wurde kein Formulartyp angegeben.'
            );
        }

        if ($config['save_key'] === '') {
            throw new RuntimeException(
                'Es wurde kein Form Key angegeben.'
            );
        }

        /*
         * ---------------------------------------------------------
         * Page
         * ---------------------------------------------------------
         */

        $context =
            (string) $config['context'];

        $allowedContexts = [
            'frontend',
            'frontend-auth',
            'backend',
        ];

        if (!in_array($context, $allowedContexts, true)) {
            throw new RuntimeException(
                'Ungültiger Page-Kontext: ' . $context
            );
        }

        $authVisibility =
            (string) $config['auth_visibility'];

        $allowedVisibility = [
            'public',
            'guest',
            'logged_in',
        ];

        if (
            !in_array(
                $authVisibility,
                $allowedVisibility,
                true
            )
        ) {
            throw new RuntimeException(
                'Ungültige Page-Sichtbarkeit: '
                    . $authVisibility
            );
        }

        $slugMode =
            (string) $config['slug_mode'];

        if (
            $config['own_page']
            && !in_array(
                $slugMode,
                ['existing', 'new'],
                true
            )
        ) {
            throw new RuntimeException(
                'Ungültiger Slug-Modus.'
            );
        }

        if (
            $config['own_page']
            && $slugMode === 'existing'
            && $config['slug'] === ''
        ) {
            throw new RuntimeException(
                'Bei einem vorhandenen Slug muss ein Slug angegeben werden.'
            );
        }

        if (
            $config['own_page']
            && $slugMode === 'new'
            && $config['new_slug'] === ''
        ) {
            throw new RuntimeException(
                'Bei einem neuen Slug muss ein neuer Slug angegeben werden.'
            );
        }

        if (
            $config['own_page']
            && $slugMode === 'new'
            && !preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                (string) $config['new_slug']
            )
        ) {
            throw new RuntimeException(
                'Der neue Slug ist ungültig. Erlaubt sind Kleinbuchstaben, Zahlen und Bindestriche.'
            );
        }

        if ($config['template'] === '') {
            throw new RuntimeException(
                'Das Page-Template darf nicht leer sein.'
            );
        }

        /*
         * ---------------------------------------------------------
         * Navigation
         * ---------------------------------------------------------
         */

        if ($config['create_navigation']) {
            if ($config['navigation_context_id'] === '') {
                throw new RuntimeException(
                    'Für die Navigation muss ein context_id angegeben werden.'
                );
            }

            if ($config['navigation_title'] === '') {
                throw new RuntimeException(
                    'Für die Navigation muss ein Titel angegeben werden.'
                );
            }

            $navigationAlign =
                (string) $config['navigation_align'];

            if (
                !in_array(
                    $navigationAlign,
                    ['left', 'right'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Ungültige Navigation-Ausrichtung.'
                );
            }

            $navigationVisibility =
                (string) $config['navigation_auth_visibility'];

            if (
                !in_array(
                    $navigationVisibility,
                    $allowedVisibility,
                    true
                )
            ) {
                throw new RuntimeException(
                    'Ungültige Navigation-Sichtbarkeit: '
                        . $navigationVisibility
                );
            }

            if (
                $config['navigation_parent_id'] !== ''
                && !preg_match(
                    '/^[a-f0-9-]{36}$/i',
                    (string) $config['navigation_parent_id']
                )
            ) {
                throw new RuntimeException(
                    'Die Navigation parent_id ist keine gültige UUID.'
                );
            }

            if (
                $config['navigation_sort_order'] !== null
                && $config['navigation_sort_order'] < 0
            ) {
                throw new RuntimeException(
                    'Die Navigation sort_order darf nicht negativ sein.'
                );
            }
        }
    }

    /**
     * Flacht mögliche verschachtelte POST-Daten ab.
     *
     * @param array<string,mixed> $postData
     *
     * @return array<string,mixed>
     */
    private function flattenPostData(
        array $postData
    ): array {
        $result = [];

        foreach ($postData as $key => $value) {
            if (is_array($value)) {
                foreach (
                    $this->flattenPostData($value)
                    as $nestedKey => $nestedValue
                ) {
                    $result[$nestedKey] =
                        $nestedValue;
                }

                continue;
            }

            $result[(string) $key] =
                $value;
        }

        return $result;
    }

    /**
     * Konvertiert einen Wert in einen String.
     */
    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    /**
     * String oder NULL.
     */
    private function nullableStringValue(
        mixed $value
    ): ?string {
        $value =
            $this->stringValue($value);

        return $value === ''
            ? null
            : $value;
    }

    /**
     * Konvertiert einen Wert zuverlässig in bool.
     */
    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_float($value)) {
            return $value !== 0.0;
        }

        if (is_string($value)) {
            return in_array(
                strtolower(trim($value)),
                [
                    '1',
                    'true',
                    'yes',
                    'on',
                    'ja',
                ],
                true
            );
        }

        return false;
    }

    /**
     * Integer oder NULL.
     */
    private function nullableInt(
        mixed $value
    ): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
