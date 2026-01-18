-- Migration: Session-Besitzer hinzufügen
-- Führe dieses Script aus, um die Datenbank zu aktualisieren

-- 1. Spalte für Session-Besitzer hinzufügen
ALTER TABLE sessions
ADD COLUMN created_by_admin_id INT NULL AFTER description;

-- 2. Foreign Key zu admin_users erstellen
ALTER TABLE sessions
ADD CONSTRAINT fk_session_admin
FOREIGN KEY (created_by_admin_id) REFERENCES admin_users(id) ON DELETE CASCADE;

-- 3. Bestehende Sessions einem Admin zuordnen (falls vorhanden)
-- Diese Zeile weist alle existierenden Sessions dem ersten Admin zu
-- Passe dies ggf. manuell an, falls mehrere Admins existieren
UPDATE sessions
SET created_by_admin_id = (SELECT id FROM admin_users ORDER BY id LIMIT 1)
WHERE created_by_admin_id IS NULL;

-- 4. Spalte auf NOT NULL setzen (jetzt da alle Sessions einen Besitzer haben)
ALTER TABLE sessions
MODIFY COLUMN created_by_admin_id INT NOT NULL;
