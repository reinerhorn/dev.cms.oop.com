DELIMITER $$

-- ==========================================================
-- 🧹 Schritt 1: Alte Trigger entfernen (falls vorhanden)
-- ==========================================================
DROP TRIGGER IF EXISTS trg_navigation_sort_after_insert $$
DROP TRIGGER IF EXISTS trg_navigation_sort_after_update $$
DROP TRIGGER IF EXISTS trg_navigation_after_insert $$
DROP TRIGGER IF EXISTS trg_navigation_after_update $$
DROP TRIGGER IF EXISTS trg_navigation_after_delete $$


-- ==========================================================
-- 🧩 Schritt 2: Neue Trigger anlegen
-- ==========================================================

-- 🔹 Sortierung nach Insert
CREATE TRIGGER trg_navigation_sort_after_insert
AFTER INSERT ON navigation
FOR EACH ROW
BEGIN
  UPDATE navigation n
  JOIN (
      SELECT nav_uuid,
             ROW_NUMBER() OVER (PARTITION BY parent_id ORDER BY sort_order ASC, nav_uuid ASC) AS new_order
      FROM navigation
  ) r ON n.nav_uuid = r.nav_uuid
  SET n.sort_order = r.new_order - 1;
END $$


-- 🔹 Sortierung nach Update
CREATE TRIGGER trg_navigation_sort_after_update
AFTER UPDATE ON navigation
FOR EACH ROW
BEGIN
  UPDATE navigation n
  JOIN (
      SELECT nav_uuid,
             ROW_NUMBER() OVER (PARTITION BY parent_id ORDER BY sort_order ASC, nav_uuid ASC) AS new_order
      FROM navigation
  ) r ON n.nav_uuid = r.nav_uuid
  SET n.sort_order = r.new_order - 1;
END $$


-- 🔹 Nach jedem Insert → Queue aktualisieren
CREATE TRIGGER trg_navigation_after_insert
AFTER INSERT ON navigation
FOR EACH ROW
BEGIN
  INSERT INTO navigation_reorder_queue (nav_uuid, action)
  VALUES (NEW.nav_uuid, 'insert');
END $$


-- 🔹 Nach jedem Update → Queue aktualisieren
CREATE TRIGGER trg_navigation_after_update
AFTER UPDATE ON navigation
FOR EACH ROW
BEGIN
  INSERT INTO navigation_reorder_queue (nav_uuid, action)
  VALUES (NEW.nav_uuid, 'update');
END $$


-- 🔹 Nach jedem Delete → Queue aktualisieren
CREATE TRIGGER trg_navigation_after_delete
AFTER DELETE ON navigation
FOR EACH ROW
BEGIN
  INSERT INTO navigation_reorder_queue (nav_uuid, action)
  VALUES (OLD.nav_uuid, 'delete');
END $$

DELIMITER ;