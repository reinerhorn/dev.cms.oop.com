<?php

declare(strict_types=1);

namespace CMS\Application\FormAction;

use RuntimeException;
use mysqli;
use CMS\Security\AccessResolver;

final class FormActionDispatcher
{
    private mysqli $db;
    private AccessResolver $accessResolver;

    public function __construct(
        mysqli $db,
        AccessResolver $accessResolver
    ) {
        $this->db = $db;
        $this->accessResolver = $accessResolver;
    }

    /**
     * Verteilt eine POST-Formularaktion an den zuständigen Handler.
     *
     * Wichtig:
     *
     * action = Button-/Form-Aktion, z.B. save
     * form_id = Formular-ID, z.B. generator_form
     *
     * Für CRUD-Formulare wird der Handler anhand der form_id
     * bzw. des daraus abgeleiteten plugin_key gesucht.
     */
    public function dispatch(
        array $postData,
        array $pageMeta
    ): array {
        error_log('========== DISPATCH START ==========');
        error_log('POST DATA: ' . print_r($postData, true));
        error_log('POST SESSION ID: ' . session_id());
        error_log(
            'POST TOKEN: '
            . ($postData['_csrf'] ?? 'NULL')
        );
        error_log(
            'SESSION TOKEN: '
            . ($_SESSION['_csrf'] ?? 'NULL')
        );

        try {
            /*
             * ---------------------------------------------------------
             * 1. Action und Form-ID auslesen
             * ---------------------------------------------------------
             */
            $action = trim(
                (string) (
                    $postData['action']
                    ?? ''
                )
            );

            $formId = trim(
                (string) (
                    $postData['form_id']
                    ?? ''
                )
            );

            if ($action === '') {
                throw new RuntimeException(
                    'Keine action übergeben'
                );
            }

            /*
             * ---------------------------------------------------------
             * 2. CSRF prüfen
             * ---------------------------------------------------------
             */
            if (
                empty($postData['_csrf'])
                || empty($_SESSION['_csrf'])
                || !hash_equals(
                    (string) $_SESSION['_csrf'],
                    (string) $postData['_csrf']
                )
            ) {
                throw new RuntimeException(
                    'Invalid CSRF token'
                );
            }

            /*
             * ---------------------------------------------------------
             * 3. Plugin-Key bestimmen
             * ---------------------------------------------------------
             *
             * Grundsätzlich ist die Action der Plugin-Key.
             *
             * Bei Formularaktionen wie save/delete/update wird
             * jedoch anhand der form_id das konkrete Formular-Plugin
             * bestimmt.
             */
            $pluginKey = $action;

            if (
                $formId !== ''
                && in_array(
                    $action,
                    [
                        'save',
                        'delete',
                        'update',
                    ],
                    true
                )
            ) {
                /*
                 * generator_form
                 *       ↓
                 * generator
                 *
                 * user_form
                 *       ↓
                 * user
                 */
                $normalizedFormId = preg_replace(
                    '/_form$/',
                    '',
                    $formId
                );

                if (
                    $normalizedFormId !== null
                    && $normalizedFormId !== ''
                ) {
                    $pluginKey = str_replace(
                        '_',
                        '-',
                        $normalizedFormId
                    );
                }
            }

            error_log(
                'ACTION RAW: '
                . $action
            );

            error_log(
                'FORM_ID RAW: '
                . $formId
            );

            error_log(
                'NORMALIZED FORM ID: '
                . ($normalizedFormId ?? 'NULL')
            );

            error_log(
                'PLUGIN KEY: '
                . $pluginKey
            );

            /*
             * ---------------------------------------------------------
             * 4. Handler aus plugin laden
             * ---------------------------------------------------------
             */
            $stmt = $this->db->prepare(
                "
                SELECT
                    handler_class,
                    module,
                    ordner_type
                FROM plugin
                WHERE plugin_key = ?
                  AND is_active = 1
                LIMIT 1
                "
            );

            if (!$stmt) {
                throw new RuntimeException(
                    'SQL Prepare fehlgeschlagen: '
                    . $this->db->error
                );
            }

            $stmt->bind_param(
                's',
                $pluginKey
            );

            if (!$stmt->execute()) {
                $error = $stmt->error;

                $stmt->close();

                throw new RuntimeException(
                    'SQL Execute fehlgeschlagen: '
                    . $error
                );
            }

            $result = $stmt->get_result();

            if (!$result) {
                $stmt->close();

                throw new RuntimeException(
                    'get_result() fehlgeschlagen'
                );
            }

            $row = $result->fetch_assoc();

            $stmt->close();

            error_log(
                'PLUGIN QUERY RESULT: '
                . print_r($row, true)
            );

            if (!$row) {
                throw new RuntimeException(
                    'Kein Handler für plugin_key: '
                    . $pluginKey
                );
            }

            /*
             * ---------------------------------------------------------
             * 5. Handler-Klasse bestimmen
             * ---------------------------------------------------------
             */
            $type = trim(
                (string) (
                    $row['ordner_type']
                    ?? 'FormAction'
                )
            );

            $module = trim(
                (string) (
                    $row['module']
                    ?? ''
                )
            );

            $handlerClass = trim(
                (string) (
                    $row['handler_class']
                    ?? ''
                )
            );

            if ($handlerClass === '') {
                throw new RuntimeException(
                    'Kein handler_class für plugin_key: '
                    . $pluginKey
                );
            }

            /*
             * Normalisierung von Typ und Modul.
             */
            $type = str_replace(
                ' ',
                '',
                ucwords(
                    str_replace(
                        [
                            '-',
                            '_',
                        ],
                        ' ',
                        $type
                    )
                )
            );

            $module = str_replace(
                ' ',
                '',
                ucwords(
                    str_replace(
                        [
                            '-',
                            '_',
                        ],
                        ' ',
                        $module
                    )
                )
            );

            /*
             * Handler normalisieren.
             *
             * Sowohl
             *
             * GenerateAllGenerator
             *
             * als auch
             *
             * \GenerateAllGenerator
             *
             * und
             *
             * GenerateAllGenerator.php
             *
             * werden akzeptiert.
             */
            $handlerClass = ltrim(
                $handlerClass,
                '\\'
            );

            $handlerClass = preg_replace(
                '/\.php$/i',
                '',
                $handlerClass
            );

            if ($handlerClass === null) {
                throw new RuntimeException(
                    'Ungültiger Handlername für plugin_key: '
                    . $pluginKey
                );
            }

            /*
             * Wenn die Datenbank bereits einen vollständigen
             * Namespace enthält, diesen direkt verwenden.
             */
            if (
                !str_contains(
                    $handlerClass,
                    '\\'
                )
            ) {
                $handlerClass =
                    'CMS\\Application\\'
                    . trim($type, '\\')
                    . '\\'
                    . trim($module, '\\')
                    . '\\'
                    . $handlerClass;
            }

            /*
             * Namespace nochmals sauber normalisieren.
             *
             * Verhindert:
             *
             * CMS\Application\FormData\Generator\\GenerateAllGenerator
             */
            $handlerClass = preg_replace(
                '/\\\\+/',
                '\\\\',
                $handlerClass
            );

            if ($handlerClass === null) {
                throw new RuntimeException(
                    'Ungültiger Handler-Namespace.'
                );
            }

            error_log(
                '========== CLASS BUILD =========='
            );

            error_log(
                'TYPE: '
                . $type
            );

            error_log(
                'MODULE: '
                . $module
            );

            error_log(
                'HANDLER: '
                . $handlerClass
            );

            /*
             * ---------------------------------------------------------
             * 6. Prüfen, ob Klasse existiert
             * ---------------------------------------------------------
             */
            if (!class_exists($handlerClass)) {
                throw new RuntimeException(
                    'Handler nicht gefunden: '
                    . $handlerClass
                );
            }

            error_log(
                'HANDLER CLASS EXISTS: YES'
            );

            /*
             * ---------------------------------------------------------
             * 7. Handler erzeugen
             * ---------------------------------------------------------
             */
            $handler = new $handlerClass(
                $this->db
            );

            /*
             * ---------------------------------------------------------
             * 8. handle()-Methode prüfen
             * ---------------------------------------------------------
             */
            if (
                !method_exists(
                    $handler,
                    'handle'
                )
            ) {
                throw new RuntimeException(
                    'Handler hat keine handle() Methode: '
                    . $handlerClass
                );
            }

            /*
             * ---------------------------------------------------------
             * 9. POST-Daten an Handler übergeben
             * ---------------------------------------------------------
             *
             * WICHTIG:
             *
             * Es werden ALLE POST-Daten übergeben.
             *
             * Insbesondere:
             *
             * table
             * form_type
             * save_key
             * form_id
             * action
             * _csrf
             *
             * gehen hier nicht verloren.
             */
            error_log(
                'HANDLER POST DATA: '
                . print_r(
                    $postData,
                    true
                )
            );

            $result = $handler->handle(
                $postData,
                $pageMeta
            );

            error_log(
                'HANDLER RESULT: '
                . print_r(
                    $result,
                    true
                )
            );

            /*
             * ---------------------------------------------------------
             * 10. JSON / AJAX
             * ---------------------------------------------------------
             */
            if (self::isJsonRequest()) {
                return $result;
            }

            /*
             * ---------------------------------------------------------
             * 11. HTML-Request
             * ---------------------------------------------------------
             */
            $_SESSION['form_result'] = $result;

            $redirect =
                $result['redirect']
                ?? (
                    $_SERVER['HTTP_REFERER']
                    ?? '/'
                );

            error_log(
                'FAD FINAL REDIRECT: '
                . $redirect
            );

            header(
                'Location: '
                . $redirect
            );

            exit;

        } catch (RuntimeException $e) {

            error_log(
                '========== DISPATCH ERROR =========='
            );

            error_log(
                $e->getMessage()
            );

            /*
             * JSON-Request.
             */
            if (self::isJsonRequest()) {
                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }

            /*
             * Normaler HTML-Request.
             */
            $_SESSION['form_result'] = [
                'success' => false,
                'message' => $e->getMessage(),
            ];

            header(
                'Location: '
                . (
                    $_SERVER['HTTP_REFERER']
                    ?? '/'
                )
            );

            exit;
        }
    }

    /**
     * Prüft, ob es sich um einen JSON/AJAX-Request handelt.
     */
    private static function isJsonRequest(): bool
    {
        if (
            !empty(
                $_SERVER['HTTP_X_REQUESTED_WITH']
            )
            && strtolower(
                (string) $_SERVER[
                    'HTTP_X_REQUESTED_WITH'
                ]
            ) === 'xmlhttprequest'
        ) {
            return true;
        }

        if (
            !empty(
                $_SERVER['HTTP_ACCEPT']
            )
            && str_contains(
                (string) $_SERVER['HTTP_ACCEPT'],
                'application/json'
            )
        ) {
            return true;
        }

        return false;
    }
}
