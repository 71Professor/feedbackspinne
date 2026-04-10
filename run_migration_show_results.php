<?php
require_once 'config.php';

try {
    $pdo = getDB();
    $sql = file_get_contents(__DIR__ . '/migration_add_show_results.sql');
    $pdo->exec($sql);
    echo "Migration erfolgreich: show_results_to_participants hinzugefügt.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Spalte existiert bereits – Migration übersprungen.\n";
    } else {
        echo "Fehler: " . $e->getMessage() . "\n";
    }
}
