<?php
require_once 'config.php';

try {
    $pdo = getDB();

    // Migration ausführen
    $sql = file_get_contents('migration_add_chart_color.sql');

    // Prüfen, ob die Spalte bereits existiert
    $stmt = $pdo->query("SHOW COLUMNS FROM sessions LIKE 'chart_color'");
    if ($stmt->rowCount() > 0) {
        echo "Migration bereits ausgeführt. Die Spalte 'chart_color' existiert bereits.\n";
    } else {
        $pdo->exec($sql);
        echo "Migration erfolgreich ausgeführt! Die Spalte 'chart_color' wurde zur Tabelle 'sessions' hinzugefügt.\n";
    }
} catch (Exception $e) {
    echo "Fehler bei der Migration: " . $e->getMessage() . "\n";
}
