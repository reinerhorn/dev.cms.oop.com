<?php
declare(strict_types=1);
namespace CMS\Repository\Member;

class UserProfile {
    private mysqli $db;
    public string $userId;
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $phone = null;
    public ?string $street = null;
    public ?string $zip = null;
    public ?string $city = null;
    public ?string $country = null;
    public ?string $birthday = null;

    public function __construct(mysqli $db, string $userId) {
        $this->db = $db;
        $this->userId = $userId;
    }

    public function loadByUserId(): void {
        $stmt = $this->db->prepare("SELECT * FROM user_profile WHERE user_id = ?");
        $stmt->bind_param("s", $this->userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            foreach ($row as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }

    public function save(): void {
        $stmt = $this->db->prepare("REPLACE INTO user_profile (user_id, first_name, last_name, phone, street, zip, city, country, birthday)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "sssssssss",
            $this->userId,
            $this->firstName,
            $this->lastName,
            $this->phone,
            $this->street,
            $this->zip,
            $this->city,
            $this->country,
            $this->birthday
        );
        $stmt->execute();
    }

    public function renderForm(): string {
        return '
            <form method="post">
                <label>Vorname:</label><input name="first_name" value="' . htmlspecialchars($this->firstName ?? '') . '"><br>
                <label>Nachname:</label><input name="last_name" value="' . htmlspecialchars($this->lastName ?? '') . '"><br>
                <label>Telefon:</label><input name="phone" value="' . htmlspecialchars($this->phone ?? '') . '"><br>
                <label>Straße:</label><input name="street" value="' . htmlspecialchars($this->street ?? '') . '"><br>
                <label>PLZ:</label><input name="zip" value="' . htmlspecialchars($this->zip ?? '') . '"><br>
                <label>Ort:</label><input name="city" value="' . htmlspecialchars($this->city ?? '') . '"><br>
                <label>Land:</label><input name="country" value="' . htmlspecialchars($this->country ?? '') . '"><br>
                <label>Geburtstag:</label><input type="date" name="birthday" value="' . htmlspecialchars($this->birthday ?? '') . '"><br>
                <button type="submit" name="save_profile">Speichern</button>
            </form>';
    }

    public function handlePostRequest(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
            $this->firstName = $_POST['first_name'] ?? null;
            $this->lastName  = $_POST['last_name'] ?? null;
            $this->phone     = $_POST['phone'] ?? null;
            $this->street    = $_POST['street'] ?? null;
            $this->zip       = $_POST['zip'] ?? null;
            $this->city      = $_POST['city'] ?? null;
            $this->country   = $_POST['country'] ?? null;
            $this->birthday  = $_POST['birthday'] ?? null;
            $this->save();
        }
    }
}

