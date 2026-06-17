
final class FooterImagesHandler implements CrudHandlerInterface
{
    public function __construct(
        private \mysqli $db
    ) {}

    public function load(string $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM footer_images WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?? [];
    }

    public function save(array $data): string
    {
        $id = $this->uuid();

        $stmt = $this->db->prepare("
            INSERT INTO footer_images (id, image_url, link_url, alt_text, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssi",
            $id,
            $data['image_url'],
            $data['link_url'],
            $data['alt_text'],
            $data['sort_order']
        );

        $stmt->execute();

        return $id;
    }

    public function update(string $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE footer_images
            SET image_url = ?, link_url = ?, alt_text = ?, sort_order = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "sssis",
            $data['image_url'],
            $data['link_url'],
            $data['alt_text'],
            $data['sort_order'],
            $id
        );

        return $stmt->execute();
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM footer_images WHERE id = ?");
        $stmt->bind_param("s", $id);

        return $stmt->execute();
    }

    private function uuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}