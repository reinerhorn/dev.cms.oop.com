-- =========================================================
-- ♻️  Wiederherstellungsskript: Re-Add aller Fremdschlüssel auf login_users.id
-- =========================================================
-- Datenbank: dbs_cms_oop
-- Erstelldatum: 2025-10-30 06:25:07
-- =========================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE orders
ADD CONSTRAINT fk_orders_user
FOREIGN KEY (user_id)
REFERENCES login_users(id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE login_agb_log
ADD CONSTRAINT fk_login_agb_log_user
FOREIGN KEY (user_id)
REFERENCES login_users(id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE addresses
ADD CONSTRAINT fk_addresses_user
FOREIGN KEY (user_id)
REFERENCES login_users(id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE customer_addresses
ADD CONSTRAINT fk_customer_addresses_user
FOREIGN KEY (user_id)
REFERENCES login_users(id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE user_profile
ADD CONSTRAINT fk_user_profile_user
FOREIGN KEY (user_id)
REFERENCES login_users(id)
ON DELETE CASCADE
ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
