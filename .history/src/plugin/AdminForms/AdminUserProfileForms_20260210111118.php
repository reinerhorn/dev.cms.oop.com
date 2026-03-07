<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminUserProfileForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================
        // DELETE
        // ==========================
        if ($action === 'delete_user_profile') {

            $userId = (string)($data['user_id'] ?? '');
            if ($userId === '') {
                return ['status'=>'error','message'=>'User-ID fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM user_profile WHERE user_id = ?"
            );
            $stmt->bind_param('s', $userId);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Profil gelöscht'];
        }

        // ==========================
        // SAVE (INSERT / UPDATE)
        // ==========================
        if ($action === 'save_user_profile') {

            $userId = (string)($data['user_id'] ?? '');
            if ($userId === '') {
                return ['status'=>'error','message'=>'User-ID fehlt'];
            }

            $firstName = (string)($data['first_name'] ?? '');
            $lastName  = (string)($data['last_name'] ?? '');
            $email     = (string)($data['email'] ?? '');
            $phone     = (string)($data['phone'] ?? '');
            $street    = (string)($data['street'] ?? '');
            $zip       = (string)($data['zip'] ?? '');
            $city      = (string)($data['city'] ?? '');
            $country   = (string)($data['country'] ?? '');
            $birthday  = (string)($data['birthday'] ?? '');

            // existiert?
            $check = $db->prepare(
                "SELECT user_id FROM user_profile WHERE user_id = ? LIMIT 1"
            );
            $check->bind_param('s', $userId);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE user_profile SET
                        first_name = ?, last_name = ?, email = ?, phone = ?,
                        street = ?, zip = ?, city = ?, country = ?, birthday = ?
                     WHERE user_id = ?"
                );
                $stmt->bind_param(
                    'ssssssssss',
                    $firstName,
                    $lastName,
                    $email,
                    $phone,
                    $street,
                    $zip,
                    $city,
                    $country,
                    $birthday,
                    $userId
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO user_profile
                        (user_id, first_name, last_name, email, phone, street, zip, city, country, birthday)
                     VALUES (?,?,?,?,?,?,?,?,?,?)"
                );
                $stmt->bind_param(
                    'ssssssssss',
                    $userId,
                    $firstName,
                    $lastName,
                    $email,
                    $phone,
                    $street,
                    $zip,
                    $city,
                    $country,
                    $birthday
                );
            }

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Profil gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
