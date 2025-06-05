<?php
class Navigation {
    private $db;
    private $language;
    private $role;
    private $page;

    public function __construct($db, $language, $role, $page = null) {
        $this->db = $db;
        $this->language = $language;
        $this->role = $role;
        $this->page = $page;
    }

    public function render(): string {
        $sql = "SELECT label, link, css FROM navigation WHERE language = ? AND (role = ? OR role IS NULL) AND type = 'header' ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $this->language, $this->role);
        $stmt->execute();
        $result = $stmt->get_result();

        $output = '<nav class="main-navigation"><ul>';
        while ($row = $result->fetch_assoc()) {
            $css = htmlspecialchars($row['css']);
            $link = htmlspecialchars($row['link']);
            $label = htmlspecialchars($row['label']);
            $output .= "<li class=\"$css\"><a href=\"$link\">$label</a></li>";
        }
        $output .= '</ul></nav>';
        return $output;
    }
}
