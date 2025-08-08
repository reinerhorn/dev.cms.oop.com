<?php

class ToggleFlagRepository
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function getStatus(?int $userId, string $key): bool
    {
        $stmt = $this->db->prepare(
            "SELECT is_enabled FROM toggle_flags WHERE user_id = ? AND toggle_key = ? LIMIT 1"
        );
        $stmt->bind_param("is", $userId, $key);
        $stmt->execute();
        $result = $stmt->get_result();

        return ($row = $result->fetch_assoc()) ? (bool)$row['is_enabled'] : false;
    }

    public function setStatus(?int $userId, string $key, bool $enabled): void
    {
        $intEnabled = $enabled ? 1 : 0;

        $stmt = $this->db->prepare("
            INSERT INTO toggle_flags (user_id, toggle_key, is_enabled, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), updated_at = NOW()
        ");
        $stmt->bind_param("isi", $userId, $key, $intEnabled);
        $stmt->execute();
    }
}
