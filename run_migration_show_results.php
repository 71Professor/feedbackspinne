<?php
require_once 'config.php';

try {
    $pdo = getDB();
    $sql = file_get_contents(__DIR__ . '/migration_add_show_results.sql');
    if ($sql === false) {
        throw new Exception('SQL-Migrationsdatei konnte nicht gelesen werden.');
    }
    $pdo->exec($sql);
    echo "Migration erfolgreich: show_results_to_participants hinzugefügt.\n";
    exit(0);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Spalte existiert bereits – Migration übersprungen.\n";
        exit(0);
    } else {
        echo "Fehler: " . $e->getMessage() . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
    exit(1);
}
