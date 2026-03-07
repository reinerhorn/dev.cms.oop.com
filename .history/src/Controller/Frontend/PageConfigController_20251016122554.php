<?php

namespace CMS\Controller\Frontend;

use CMS\Service\PageConfigService;
use CMS\Core\View;

class AdminPageConfigController
{
    private PageConfigService $service;

    public function __construct()
    {
        $this->service = new PageConfigService();
    }

    public function index(): void
    {
        $pageConfigs = $this->service->getAllPageConfigs();
        $plugins = $this->service->getAllPlugins();

        View::render('admin_page_config.twig', [
            'pageConfigs' => $pageConfigs,
            'plugins' => $plugins
        ]);
    }

    public function updatePlugin(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plugin_uuid'])) {
            foreach ($_POST['plugin_uuid'] as $pageConfigUuid => $pluginUuid) {
                $this->service->updatePluginForPageConfig($pageConfigUuid, $pluginUuid);
            }
        }

        header('Location: ?action=adminPageConfig&success=1');
        exit;
    }
}