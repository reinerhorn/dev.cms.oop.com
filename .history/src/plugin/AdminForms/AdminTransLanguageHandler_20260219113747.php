<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;
use mysqli;

final class AdminTransLanguageHandler
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = CMSApp::getDb();
    }

    public function handle(array $data, array $pageMeta): array
    {
        $action = $data['action'] ?? null;

        // Wenn explizit delete gedrückt wurde
        if ($action === 'delete_trans_language') {
            return $this->delete($data);
        }

        // Standard: speichern (create oder update)
        return $this->save($data);
    }

    /**
     * ==========================
     * SAVE (INSERT / UPDATE)
     * ==========================
     */
    private function save(array $data): array
    {
        $id       = trim((string)($data['id'] ?? ''));
        $label    = trim((string)($data['label'] ?? ''));
        $flagPath = trim((string)($data['flag_path'] ?? ''));

        if ($id === '' || $label === '') {
            return $this->error('ID oder Label fehlt');
        }

        // Prüfen ob Datensatz existiert
        $check = $this->db->prepare(
            "SELECT id FROM trans_language WHERE id = ? LIMIT 1"
        );
        $check->bind_param('s', $id);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();

        if ($exists) {
            $stmt = $this->db->prepare(
                "UPDATE trans_language
                 SET label = ?, flag_path = ?
                 WHERE id = ?"
            );
            $stmt->bind_param('sss', $label, $flagPath, $id);
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO trans_language (id, label, flag_path)
                 VALUES (?, ?, ?)"
            );
            $stmt->bind_param('sss', $id, $label, $flagPath);
        }

        $stmt->execute();
        $stmt->close();

        return $this->success('Sprache gespeichert');
    }

    /**
     * ==========================
     * DELETE
     * ==========================
     */
    private function delete(array $data): array
    {
        $id = trim((string)($data['id'] ?? ''));

        if ($id === '') {
            return $this->error('Language-ID fehlt');
        }

        $stmt = $this->db->prepare(
            "DELETE FROM trans_language WHERE id = ?"
        );
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $stmt->close();

        return $this->success('Sprache gelöscht');
    }

    /**
     * Response Helper
     */
    private function success(string $message): array
    {
        return [
            'status'  => 'ok',
            'message' => $message
        ];
    }

    private function error(string $message): array
    {
        return [
            'status'  => 'error',
            'message' => $message
        ];
    }
}
