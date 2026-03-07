// -------------------------------------------------
// 4) Form Action Dispatch (formulargetrieben, sauber isoliert)
// -------------------------------------------------
error_log('RENDER HIT');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Formular muss eine form_id besitzen
    if (empty($_POST['form_id'])) {
        throw new RuntimeException('Form-ID fehlt.');
    }

    // Seite muss grundsätzlich FormActions erlauben
    if (empty($pageMeta['form_action'])) {
        throw new RuntimeException('Keine FormAction für diese Seite definiert.');
    }

    // Nur dispatchen, wenn dieses Formular zur Seite gehört
    if ($_POST['form_id'] !== $pageMeta['form_action']) {
        // anderes Formular auf derselben Seite → ignorieren
    } else {

        error_log('DISPATCH FORM_ACTION RAW: >' . $_POST['form_id'] . '<');

        $result = PageFormActionDispatcher::dispatch(
            $_POST['form_id'],   // einzig gültige Quelle
            $_POST,
            $pageMeta
        );

        $_SESSION['form_result'] = $result;

        // POST-Redirect-GET sauber ausführen
        if (!empty($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
    }
}