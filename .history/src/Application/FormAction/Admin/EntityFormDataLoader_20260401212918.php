        /*
        ==========================
        BUTTONS LADEN (FINAL)
        ==========================
        */

        $formId = $block['config']['form_id'] ?? null;

        if ($formId) {

            error_log('BUTTON LOADER FORM_ID: ' . $formId);

            $buttons = [];

            $stmt = $db->prepare("
                SELECT 
                    b.button_type,
                    b.variant,
                    b.button_action,
                    b.label_key,
                    b.confirm_required
                FROM form_button fb
                JOIN ui_button b ON b.button_id = fb.button_id
                WHERE fb.form_id = ?
                AND b.enabled = 1
                ORDER BY fb.sort_order ASC
            ");

            if ($stmt) {
                $stmt->bind_param("s", $formId);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {

                    $buttons[] = [
                        'button_type'      => $row['button_type'] ?? 'submit',
                        'variant'          => $row['variant'] ?? 'primary',
                        'action'           => $row['button_action'] ?? null,
                        'label_key'        => $row['label_key'] ?? 'SUBMIT',
                        'confirm_required' => (bool)($row['confirm_required'] ?? false),
                    ];
                }

                $stmt->close();
            }

            error_log('BUTTONS LOADED: ' . print_r($buttons, true));

            $block['buttons'] = $buttons;
        }
