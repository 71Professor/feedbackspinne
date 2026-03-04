-- Migration: Freitext-Feedback-Felder pro Dimension
-- Fügt die Spalte text_values zur submissions-Tabelle hinzu,
-- um optionale Freitextkommentare je Dimension zu speichern.

ALTER TABLE submissions
    ADD COLUMN text_values TEXT NULL DEFAULT NULL AFTER `values`;
