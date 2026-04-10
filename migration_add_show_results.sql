-- Migration: Ergebnis-Sichtbarkeit für Teilnehmende
-- Fügt das Feld show_results_to_participants zur sessions-Tabelle hinzu.
-- Default 0 = Ergebnis nicht sichtbar (Opt-in durch Ersteller).

ALTER TABLE sessions
    ADD COLUMN show_results_to_participants TINYINT(1) NOT NULL DEFAULT 0;
