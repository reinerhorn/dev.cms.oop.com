<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminProductsForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================
        // DELETE
        // ==========================
        if ($action === 'delete_product') {

            $id = (string)($data['id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'Produkt-ID fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM products WHERE id = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Produkt gelöscht'];
        }

        // ==========================
        // SAVE (INSERT / UPDATE)
        // ==========================
        if ($action === 'save_product') {

            $id = (string)($data['id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'Produkt-ID fehlt'];
            }

            // existiert?
            $check = $db->prepare(
                "SELECT id FROM products WHERE id = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            $name        = (string)($data['name'] ?? '');
            $description = (string)($data['description'] ?? '');
            $price       = (float)($data['price'] ?? 0);
            $stock       = (int)($data['stock'] ?? 0);
            $categoryId  = (string)($data['category_id'] ?? '');

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE products SET
                        name = ?,
                        description = ?,
                        price = ?,
                        stock = ?,
                        category_id = ?
                     WHERE id = ?"
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO products
                    (id, name, description, price, stock, category_id)
                    VALUES (?,?,?,?,?,?)"
                );
            }

            $stmt->bind_param(
                'ssdis s',
                $name,
                $description,
                $price,
                $stock,
                $categoryId,
                $id
            );

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Produkt gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
