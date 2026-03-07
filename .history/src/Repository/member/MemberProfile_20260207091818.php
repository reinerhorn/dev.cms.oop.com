<?php
class MemberProfile {
    private mysqli $db;
    private string $userId;

    public function __construct(mysqli $db, string $userId) {
        $this->db = $db;
        $this->userId = $userId;
    }

    public function getProfile(): ?array {
        $stmt = $this->db->prepare("SELECT * FROM user_profile WHERE user_id = ?");
        $stmt->bind_param("s", $this->userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    public function saveProfile(array $data): bool {
        $existing = $this->getProfile();

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE user_profile 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? 
                WHERE user_id = ?
            ");
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO user_profile (first_name, last_name, email, phone, address, user_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
        }

        $stmt->bind_param(
            "ssssss",
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $this->userId
        );

        return $stmt->execute();
    }

    public function renderForm(): string {
        $profile = $this->getProfile() ?? [
            'first_name' => '',
            'last_name'  => '',
            'email'      => '',
            'phone'      => '',
            'address'    => ''
        ];

        ob_start();
        echo "<form method=\"post\">
            <label>Vorname:</label>
            <input type=\"text\" name=\"first_name\" value=\"" . htmlspecialchars($profile['first_name']) . "\" required>

            <label>Nachname:</label>
            <input type=\"text\" name=\"last_name\" value=\"" . htmlspecialchars($profile['last_name']) . "\" required>

            <label>E-Mail:</label>
            <input type=\"email\" name=\"email\" value=\"" . htmlspecialchars($profile['email']) . "\" required>

            <label>Telefon:</label>
            <input type=\"text\" name=\"phone\" value=\"" . htmlspecialchars($profile['phone']) . "\">

            <label>Adresse:</label>
            <textarea name=\"address\">" . htmlspecialchars($profile['address']) . "</textarea>

            <button type=\"submit\" name=\"save_profile\">Speichern</button>
        </form>";
        return ob_get_clean();
    }
}
