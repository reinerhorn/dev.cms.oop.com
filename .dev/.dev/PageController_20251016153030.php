<?php
namespace CMS\Core\Controller;

use CMS\Core\CMSApp;
use CMS\Core\CMSAppFrontend;
use CMS\Repository\Navigation\Navigation;
use CMS\Core\LanguageSelector;
use CMS\Plugin\PluginPlaintext;
use Twig\Environment;

class PageController
{
    private Environment $twig;
    private \mysqli $db;
    private string $frontendClass;
    private bool $debug;

    public function __construct(Environment $twig, \mysqli $db, string $frontendClass, bool $debug = false)
    {
        $this->twig = $twig;
        $this->db = $db;
        $this->frontendClass = $frontendClass;
        $this->debug = $debug;
    }

    public function handleRequest(string $language, string $slug): void
    {
        // Sprache im CMSApp setzen (für Navigation und andere Komponenten)
        if (method_exists(CMSApp::class, 'setLanguage')) {
            CMSApp::setLanguage($language);
        }

        // 1️⃣ Page-Daten laden
        $pageData = $this->loadPageData($slug);
        if (!$pageData) {
            http_response_code(404);
            echo $this->twig->render('errors/404.twig', [
                'language' => $language,
                'slug' => $slug,
            ]);
            return;
        }

        // 2️⃣ Plugin-Inhalte laden
        $contents = $this->loadPluginContents($pageData['page_uuid'], $language);

        // 3️⃣ Navigation & LanguageSelector vorbereiten
        $navigation = new Navigation($this->db, $language, CMSApp::getRole(), $pageData['page_uuid']);
        $languageSelector = new LanguageSelector($this->db, $language);
        $languageSelectorData = [
            'label'     => $languageSelector->getLabel(),
            'languages' => $languageSelector->getLanguages(),
        ];

        // 4️⃣ Daten an zentrales Frontend weitergeben
        $frontendClass = $this->frontendClass;
        echo $frontendClass::render($this->twig, $pageData['page_uuid'], null, [
            'language'           => $language,
            'slug'               => $slug,
            'contents'           => $contents,
            'navigation'         => $navigation->getItems(),
            'language_selector'  => $languageSelectorData,
            'debug'              => $this->debug,
        ]);
    }

    private function loadPageData(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT page_uuid FROM page WHERE slug = ? AND enabled = 1 LIMIT 1");
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc() ?: null;
    }

    private function loadPluginContents(string $pageUuid, string $language): array
    {
        $contents = [];
        $stmt = $this->db->prepare("
            SELECT pc.page_config_uuid, pc.fk_plugin_uuid, pc.plugin_content_uuid, pc.idx, p.name AS plugin_label
            FROM page_config pc
            LEFT JOIN plugin p ON pc.fk_plugin_uuid = p.plugin_uuid
            WHERE pc.fk_page_uuid = ?
            ORDER BY pc.idx ASC
        ");
        $stmt->bind_param('s', $pageUuid);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($config = $res->fetch_assoc()) {
            if ($config['plugin_label'] === 'Plaintext') {
                $content = PluginPlaintext::getContent(
                    $this->db,
                    $config['plugin_content_uuid'],
                    $pageUuid,
                    $language
                );
                if (!empty($content)) {
                    $contents[] = $content;
                }
            }
        }

        return $contents;
    }
}