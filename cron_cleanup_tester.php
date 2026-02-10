#!/usr/bin/env php
<?php
/**
 * Cronjob: Löscht stündlich alle Sessions und Submissions
 * des Tester-Accounts (admin_id = 2), außer geschützte Sessions.
 *
 * Geschützte Sessions (werden NICHT gelöscht):
 * - 5921: "Kompetenzentwicklung: Digitale Tools für die Kita-Praxis"
 * - 0357: "Workshop-Rahmenbedingungen"
 * - 9732: "Online-Workshop: Demokratiebildung für Jugendliche"
 *
 * Einrichtung (stündlich):
 * crontab -e
 * 0 * * * * /usr/bin/php /home/user/feedbackspinne/cron_cleanup_tester.php >> /home/user/feedbackspinne/cron_cleanup.log 2>&1
 */

// config.php startet eine Session — im CLI-Modus unnötig, aber harmlos
require_once __DIR__ . '/config.php';

const TESTER_ADMIN_ID = 2;

// Geschützte Session-Codes (als Array für einfache Erweiterung)
const PROTECTED_SESSION_CODES = ['5921', '0357', '9732'];

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // 1. IDs der geschützten Sessions ermitteln
    $placeholders = str_repeat('?,', count(PROTECTED_SESSION_CODES) - 1) . '?';
    $stmtProtected = $pdo->prepare("
        SELECT id, code, title
        FROM sessions
        WHERE code IN ({$placeholders})
    ");
    $stmtProtected->execute(PROTECTED_SESSION_CODES);
    $protectedSessions = $stmtProtected->fetchAll(PDO::FETCH_ASSOC);
    $protectedIds = array_column($protectedSessions, 'id');

    // 2. Submissions löschen, die zu Tester-Sessions gehören (außer geschützte)
    if (!empty($protectedIds)) {
        $idPlaceholders = str_repeat('?,', count($protectedIds) - 1) . '?';
        $stmtSub = $pdo->prepare("
            DELETE FROM submissions
            WHERE session_id IN (
                SELECT id FROM sessions
                WHERE created_by_admin_id = ?
                AND id NOT IN ({$idPlaceholders})
            )
        ");
        $params = array_merge([TESTER_ADMIN_ID], $protectedIds);
        $stmtSub->execute($params);
    } else {
        // Keine geschützten Sessions vorhanden
        $stmtSub = $pdo->prepare("
            DELETE FROM submissions
            WHERE session_id IN (
                SELECT id FROM sessions
                WHERE created_by_admin_id = ?
            )
        ");
        $stmtSub->execute([TESTER_ADMIN_ID]);
    }
    $deletedSubmissions = $stmtSub->rowCount();

    // 3. Tester-Sessions löschen (außer geschützte)
    if (!empty($protectedIds)) {
        $stmtSes = $pdo->prepare("
            DELETE FROM sessions
            WHERE created_by_admin_id = ?
            AND id NOT IN ({$idPlaceholders})
        ");
        $stmtSes->execute($params);
    } else {
        $stmtSes = $pdo->prepare("
            DELETE FROM sessions
            WHERE created_by_admin_id = ?
        ");
        $stmtSes->execute([TESTER_ADMIN_ID]);
    }
    $deletedSessions = $stmtSes->rowCount();

    $pdo->commit();

    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] Cleanup: {$deletedSessions} Sessions, {$deletedSubmissions} Submissions gelöscht.\n";

    // Info über geschützte Sessions ausgeben
    if (!empty($protectedSessions)) {
        echo "[{$timestamp}] Geschützte Sessions: ";
        $codes = array_column($protectedSessions, 'code');
        echo implode(', ', $codes) . "\n";
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] FEHLER: " . $e->getMessage() . "\n";
    exit(1);
}