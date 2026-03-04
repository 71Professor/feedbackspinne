<?php
/**
 * Migration: text_values-Spalte zur submissions-Tabelle hinzufügen
 * Einmalig ausführen, danach kann diese Datei gelöscht werden.
 */
require_once __DIR__ . '/config.php';

$pdo = getDB();

// Prüfen ob Spalte bereits existiert
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'submissions'
      AND COLUMN_NAME = 'text_values'
");
$stmt->execute();
$exists = (bool) $stmt->fetchColumn();

if ($exists) {
    echo "Spalte 'text_values' existiert bereits – Migration übersprungen.\n";
    exit(0);
}

$pdo->exec("ALTER TABLE submissions ADD COLUMN text_values TEXT NULL DEFAULT NULL AFTER `values`");
echo "Migration erfolgreich: Spalte 'text_values' wurde zur Tabelle 'submissions' hinzugefügt.\n";
