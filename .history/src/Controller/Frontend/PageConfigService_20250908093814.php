<?php

namespace CMS\Service;

use mysqli;
use Exception;

class PageConfigService
{
    private mysqli $connection;

    public function __construct()
    {
        $this->connection = getDbConnection();
        if (!$this->connection instanceof mysqli) {
            throw new Exception("Keine gültige MySQLi-Verbindung.");
        }
    }

    public function getAllPageConfigs(): array
    {
        $data = [];
        $sql = "SELECT page_config_uuid, page_title, fk_plugin_uuid FROM view_page_config";
        $result = $this->connection->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }

        return $data;
    }

    public function getAllPlugins(): array
    {
        $data = [];
        $sql = "SELECT plugin_uuid, name FROM plugin ORDER BY name ASC";
        $result = $this->connection->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }

        return $data;
    }

    public function updatePluginForPageConfig(string $pageConfigUuid, string $pluginUuid): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE page_config SET fk_plugin_uuid = ? WHERE page_config_uuid = ?"
        );

        if (!$stmt) {
            throw new Exception("Fehler beim Erstellen des Statements: " . $this->connection->error);
        }

        $stmt->bind_param("ss", $pluginUuid, $pageConfigUuid);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }
}