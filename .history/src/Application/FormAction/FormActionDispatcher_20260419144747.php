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
            $formId = trim($postData['form_id'] ?? '');

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
                SELECT handler_class, module, type
                FROM plugin
                WHERE form_id = ?
                AND is_active = 1
                LIMIT 1
            ");
            $stmt->bind_param('s', $formId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            error_log('========== FORM ACTION DEBUG ==========');
            error_log('RAW DB ROW: ' . print_r($row, true));
            error_log('MODULE: ' . ($row['module'] ?? 'NULL'));
            error_log('HANDLER_CLASS: ' . ($row['handler_class'] ?? 'NULL'));

            if (!$row) {
                throw new RuntimeException('Kein Handler für form_id: ' . $formId);
            }

        // Namespace automatisch aufbauen (module + handler_class)
        error_log('========== CLASS BUILD START ==========');
            $type = $row['type'] ?? 'FormAction';
            $module = $row['module'] ?? '';
            $handlerClass = $row['handler_class'];
            error_log('MODULE BEFORE BUILD: ' . $module);
            error_log('HANDLER BEFORE BUILD: ' . $handlerClass);

            // 🔥 FIX: normalize handler class (remove leading backslashes / whitespace)
            $handlerClass = trim($handlerClass);
            $handlerClass = ltrim($handlerClass, '\\');

            error_log('HANDLER AFTER NORMALIZE: ' . $handlerClass);

            if (!str_contains($handlerClass, '\\')) {
                $handlerClass = 'CMS\\Application\\'
                    . $type . '\\'
                    . ucfirst($module)
                    . '\\'
                    . $handlerClass;
            }
            error_log('BUILT CLASS: ' . $handlerClass);

            error_log('CLASS EXISTS CHECK FOR: ' . $handlerClass);
            if (!class_exists($handlerClass)) {
                error_log('CLASS EXISTS RESULT: NO');
                throw new RuntimeException('Handler nicht gefunden: ' . $handlerClass);
            }
            error_log('CLASS EXISTS RESULT: YES');

            $result = (new $handlerClass())->handle($postData, $pageMeta);

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
