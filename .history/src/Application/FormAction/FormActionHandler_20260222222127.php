<?php

namespace CMS\Application\FormAction;

use mysqli;

class FormActionHandler
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $formId = $_POST['form_id'] ?? null;

        if (!$formId) {
            return;
        }

        // Formular-Konfiguration laden
        $stmt = $this->db->prepare("
            SELECT entity_table, config_json
            FROM p_content_formular
            WHERE form_type = ?
            LIMIT 1
        ");

        if (!$stmt) {
            error_log('FormActionHandler PREPARE ERROR: ' . $this->db->error);
            return;
        }

        $stmt->bind_param('s', $formId);
        $stmt->execute();
        $result = $stmt->get_result();
        $formRow = $result->fetch_assoc();
        $stmt->close();

        if (!$formRow) {
            return;
        }

        $entityTable = $formRow['entity_table'] ?? null;
        $configJson  = $formRow['config_json'] ?? '{}';

        if (!$entityTable) {
            return;
        }

        $config = json_decode($configJson, true);
        if (!is_array($config)) {
            error_log('FormActionHandler JSON ERROR for form: ' . $formId);
            return;
        }

        $action = $_POST['action'] ?? 'save';

        // POST-Daten bereinigen
        $data = [];
        foreach ($_POST as $key => $value) {
            if (in_array($key, ['form_id', 'action', '_csrf'], true)) {
                continue;
            }
            $data[$key] = $value;
        }

        if (empty($data)) {
            return;
        }

        if ($action === 'delete') {
            $this->handleDelete($entityTable, $data);
            return;
        }

        $this->handleSave($entityTable, $data);
    }

    private function handleSave(string $table, array $data): void
    {
        if (isset($data['id']) && !empty($data['id'])) {
            // UPDATE
            $id = $data['id'];
            unset($data['id']);

            $setParts = [];
            $types = '';
            $values = [];

            foreach ($data as $column => $value) {
                $setParts[] = "{$column} = ?";
                $types .= 's';
                $values[] = $value;
            }

            $types .= 's';
            $values[] = $id;

            $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE id = ? LIMIT 1";

            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                error_log('FormActionHandler UPDATE PREPARE ERROR: ' . $this->db->error);
                return;
            }

            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $stmt->close();

        } else {
            // INSERT
            $columns = array_keys($data);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $types = str_repeat('s', count($columns));
            $values = array_values($data);

            $sql = "INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES ({$placeholders})";

            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                error_log('FormActionHandler INSERT PREPARE ERROR: ' . $this->db->error);
                return;
            }

            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function handleDelete(string $table, array $data): void
    {
        if (empty($data['id'])) {
            return;
        }

        $id = $data['id'];

        $stmt = $this->db->prepare("DELETE FROM {$table} WHERE id = ? LIMIT 1");
        if (!$stmt) {
            error_log('FormActionHandler DELETE PREPARE ERROR: ' . $this->db->error);
            return;
        }

        $stmt->bind_param('s', $id);
        $stmt->execute();
        $stmt->close();
    }
}
