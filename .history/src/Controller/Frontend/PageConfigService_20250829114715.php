<?php

namespace CMS\Service;

class PageConfigService
{
    private \mysqli $connection;

    public function __construct()
    {
        $this->connection = getDbConnection();
    }

    public function getAllPageConfigs(): array
    {
        $data = [];
        $sql = "SELECT page_config_uuid, page_title, fk_plugin_uuid FROM view_page_config";
        $result = $this->connection->query($sql);

        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        return $data;
    }

    public function getAllPlugins(): array
    {
        $data = [];
        $sql = "SELECT plugin_uuid, name FROM plugin ORDER BY name ASC";
        $result = $this->connection->query($sql);

        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        return $data;
    }

    public function updatePluginForPageConfig(string $pageConfigUuid, string $pluginUuid): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE page_config SET fk_plugin_uuid = ? WHERE page_config_uuid = ?"
        );
        $stmt->bind_param("ss", $pluginUuid, $pageConfigUuid);
        return $stmt->execute();
    }
}