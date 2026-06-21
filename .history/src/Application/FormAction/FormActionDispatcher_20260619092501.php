<?php

namespace CMS\Application\FormAction;

use RuntimeException;
use mysqli;
use CMS\Security\AccessResolver;

final class FormActionDispatcher
{
    private mysqli $db;
    private AccessResolver $accessResolver;

    public function __construct(mysqli $db, AccessResolver $accessResolver)
    {
        $this->db = $db;
        $this->accessResolver = $accessResolver;
    }

    public function dispatch(array $postData, array $pageMeta): array
    {
        error_log('DISPATCH START');
        error_log(print_r($postData, true));
        error_log('POST SESSION ID: ' . session_id());
        error_log('POST TOKEN: ' . ($postData['_csrf'] ?? 'NULL'));
        error_log('SESSION TOKEN: ' . ($_SESSION['_csrf'] ?? 'NULL'));
        try {
            $action = trim((string)($postData['action'] ?? ''));
            $formId = trim((string)($postData['form_id'] ?? ''));

            if ($action === '') {
                throw new RuntimeException('Keine action übergeben');
            }

            $pluginKey = $action;

            // Datengetriebene Formulare: form_id + action => header_save, footer_images_save usw.
            if (
                $formId !== ''
                && in_array($action, ['save', 'delete', 'update'], true)
            ) {
                $normalizedFormId = preg_replace('/_form$/', '', $formId);

                // plugin.plugin_key verwendet Bindestriche (footer-save, header-save)
                $pluginKey = str_replace('_', '-', $normalizedFormId) . '-' . $action;
            }

            error_log('FORM ID: ' . $formId);
            error_log('NORMALIZED FORM ID: ' . ($normalizedFormId ?? 'NULL'));
            error_log('ACTION: ' . $action);
            error_log('PLUGIN KEY: ' . $pluginKey);
            error_log('PLUGIN LOOKUP SQL KEY: [' . $pluginKey . ']');

            // 1) CSRF prüfen
            if (
                empty($postData['_csrf'])
                || empty($_SESSION['_csrf'])
                || !hash_equals($_SESSION['_csrf'], $postData['_csrf'])
            ) {
                throw new RuntimeException('Invalid CSRF token');
            }

            // 2) Handler datengetrieben aus form_actions laden
            $db = $this->db;

            $stmt = $db->prepare("
                SELECT handler_class, module, ordner_type
                FROM plugin
                WHERE plugin_key = ?
                  AND is_active = 1
                LIMIT 1
            ");
            if (!$stmt) {
                throw new RuntimeException('SQL Prepare fehlgeschlagen: ' . $db->error);
            }
            $stmt->bind_param('s', $pluginKey);
            $stmt->execute();

            if ($stmt->error) {
                throw new RuntimeException('SQL Execute fehlgeschlagen: ' . $stmt->error);
            }

            $result = $stmt->get_result();

            if (!$result) {
                throw new RuntimeException('get_result() fehlgeschlagen');
            }

            $row = $result->fetch_assoc();
            $stmt->close();

            error_log('PLUGIN QUERY RESULT: ' . print_r($row, true));
            error_log('========== FORM ACTION DEBUG ==========');
            error_log('RAW DB ROW: ' . print_r($row, true));
            error_log('MODULE: ' . ($row['module'] ?? 'NULL'));
            error_log('ORDNER_TYPE: ' . ($row['ordner_type'] ?? 'NULL'));
            error_log('HANDLER_CLASS: ' . ($row['handler_class'] ?? 'NULL'));

            if (!$row) {
                error_log('FORM_ID POST: ' . ($postData['form_id'] ?? 'NULL'));
                error_log('ACTION POST: ' . ($postData['action'] ?? 'NULL'));
                throw new RuntimeException('Kein Handler für plugin_key: ' . $pluginKey);
            }

        // Namespace automatisch aufbauen (module + handler_class)
        error_log('========== CLASS BUILD START ==========');
            $type = trim((string)($row['ordner_type'] ?? 'FormAction'));
            $module = trim((string)($row['module'] ?? ''));
            $handlerClass = trim((string)($row['handler_class'] ?? ''));
            error_log('MODULE BEFORE BUILD: ' . $module);
            error_log('HANDLER BEFORE BUILD: ' . $handlerClass);

            // Normalize $type and $module
            $type = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $type)));
            $module = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $module)));

            // 🔥 FIX: normalize handler class (remove leading backslashes / whitespace)
            $handlerClass = ltrim($handlerClass, '\\');
            $handlerClass = preg_replace('/\.php$/i', '', $handlerClass);

            error_log('HANDLER AFTER NORMALIZE: ' . $handlerClass);

            if (!str_contains($handlerClass, '\\')) {
                $handlerClass = 'CMS\\Application\\'
                    . $type . '\\'
                    . $module
                    . '\\'
                    . ucfirst($handlerClass);
            }
            error_log('BUILT CLASS: ' . $handlerClass);

            error_log('FINAL TYPE: ' . $type);
            error_log('FINAL MODULE: ' . $module);
            error_log('CLASS EXISTS CHECK FOR: ' . $handlerClass);
            if (!class_exists($handlerClass)) {
                error_log('CLASS EXISTS RESULT: NO');
                throw new RuntimeException('Handler nicht gefunden: ' . $handlerClass);
            }
            error_log('CLASS EXISTS RESULT: YES');

            // 🔥 Handler bekommt alle POST-Daten (action/plugin_key ist Routing-Key)
            $handler = new $handlerClass($this->db);
            
            if (!method_exists($handler, 'handle')) {
                throw new RuntimeException('Handler hat keine handle() Methode: ' . $handlerClass);
            }

            $result = $handler->handle($postData, $pageMeta);
            error_log('HANDLER RESULT: ' . print_r($result, true));
            // AJAX / API Requests bleiben unverändert
            if (self::isJsonRequest()) {
                return $result;
            }

            // HTML Requests: POST Ownership wird hier abgeschlossen
            $_SESSION['form_result'] = $result;

            $redirect = $result['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '/');

            error_log(' FAD FINAL REDIRECT: ' . $redirect);

            header('Location: ' . $redirect);
            exit;
        } catch (RuntimeException $e) {

            error_log('DISPATCH ERROR: ' . $e->getMessage());

            // JSON-Requests (AJAX / API)
            if (self::isJsonRequest()) {
                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }

            // HTML-Requests → Redirect mit Flash-Message
            $_SESSION['form_result'] = [
                'success' => false,
                'message' => $e->getMessage(),
            ];

            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }
    }

    private static function isJsonRequest(): bool
    {
        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            return true;
        }

        if (
            !empty($_SERVER['HTTP_ACCEPT'])
            && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')
        ) {
            return true;
        }

        return false;
    }
}
