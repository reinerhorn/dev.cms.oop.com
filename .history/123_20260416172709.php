 private function buildHandlerClass(string $module, string $handlerRaw
{
    $basePath = $this->resolveBasePath('form_action');
    return rtrim($basePath, '\\') . '\\'
        . ucfirst($module)
        . '\\'
        . $handlerRaw;
}
/**
 * Holt den Namespace-Pfad aus der DB (cms_path Tabelle)
 */
private function resolveBasePath(string $type): string
{
    $stmt = $this->db->prepare("
        SELECT base_namespace
        FROM cms_path
        WHERE type = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return 'CMS\\Application\\FormAction\\'; // Fallback
    }
    $stmt->bind_param('s', $type);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!empty($result['base_namespace'])) {
        return $result['base_namespace'];
    }
    // Fallback (wichtig für Stabilität)
    return 'CMS\\Application\\FormAction\\';
}
 
 
 
 