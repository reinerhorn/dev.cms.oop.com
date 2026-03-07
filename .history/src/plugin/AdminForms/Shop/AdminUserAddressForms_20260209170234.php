<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin\Shop;

use CMS\Core\CMSApp;

final class AdminUserAddressForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================================
        // DELETE
        // ==========================================
        if ($action === 'delete_user_address') {
            $id = (string)($data['id'] ?? '');

            if ($id === '') {
                return ['status'=>'error','message'=>'ID fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM user_addresses WHERE id = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Adresse gelöscht'];
        }

        // ==========================================
        // SAVE (INSERT / UPDATE)
        // ==========================================
        if ($action === 'save_user_address') {

            $id          = (string)($data['id'] ?? '');
            $userId      = (string)($data['user_id'] ?? '');
            $type        = (string)($data['type'] ?? '');
            $name        = (string)($data['name'] ?? '');
            $street      = (string)($data['street'] ?? '');
            $postalCode  = (string)($data['postal_code'] ?? '');
            $city        = (string)($data['city'] ?? '');
            $country     = (string)($data['country'] ?? '');
            $phone       = $data['phone'] ?? null;

            if (
                $id === '' || $userId === '' || $type === '' ||
                $name === '' || $street === '' ||
                $postalCode === '' || $city === '' || $country === ''
            ) {
                return [
                    'status'  => 'error',
                    'message' => 'Pflichtfelder fehlen'
                ];
            }

            // Existiert Datensatz?
            $check = $db->prepare(
                "SELECT id FROM user_addresses WHERE id = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE user_addresses
                     SET user_id = ?, type = ?, name = ?, street = ?,
                         postal_code = ?, city = ?, country = ?, phone = ?
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    'sssssssss',
                    $userId,
                    $type,
                    $name,
                    $street,
                    $postalCode,
                    $city,
                    $country,
                    $phone,
                    $id
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO user_addresses
                     (id, user_id, type, name, street, postal_code, city, country, phone)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    'sssssssss',
                    $id,
                    $userId,
                    $type,
                    $name,
                    $street,
                    $postalCode,
                    $city,
                    $country,
                    $phone
                );
            }

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Adresse gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}