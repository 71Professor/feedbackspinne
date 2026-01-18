-- Migration: Chart-Farbe zu Sessions hinzufügen
-- Führe diese Datei aus, um die Datenbank zu aktualisieren

ALTER TABLE sessions
ADD COLUMN chart_color VARCHAR(7) DEFAULT '#7ab800'
AFTER dimensions;
