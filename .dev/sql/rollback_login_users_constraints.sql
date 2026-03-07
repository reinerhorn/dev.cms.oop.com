-- =========================================================
-- 🔁  Rollback-Skript: Entfernt alle Fremdschlüssel auf login_users.id
-- =========================================================
-- Datenbank: dbs_cms_oop
-- Erstelldatum: 2025-10-30 06:25:07
-- Zweck: Vorübergehendes Entfernen aller Beziehungen zu login_users.id
-- =========================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE orders DROP FOREIGN KEY fk_orders_user;
ALTER TABLE login_agb_log DROP FOREIGN KEY fk_login_agb_log_user;
ALTER TABLE addresses DROP FOREIGN KEY fk_addresses_user;
ALTER TABLE customer_addresses DROP FOREIGN KEY fk_customer_addresses_user;
ALTER TABLE user_profile DROP FOREIGN KEY fk_user_profile_user;

SET FOREIGN_KEY_CHECKS = 1;
