#!/usr/bin/env php
<?php
/**
 * Cronjob: Löscht stündlich alle Sessions und Submissions
 * des Tester-Accounts (admin_id = 2), außer Session ID 9 (Code 5921).
 *
 * Einrichtung (stündlich):
 * crontab -e
 * 0 * * * * /usr/bin/php /home/user/feedbackspinne/cron_cleanup_tester.php >> /home/user/feedbackspinne/cron_cleanup.log 2>&1
 */

// config.php startet eine Session — im CLI-Modus unnötig, aber harmlos
require_once __DIR__ . '/config.php';

const TESTER_ADMIN_ID = 2;
const PROTECTED_SESSION_ID = 9; // Code 5921, "Kompetenzentwicklung: Digitale Tools für die Kita-Praxis"

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // 1. Submissions löschen, die zu Tester-Sessions gehören (außer geschützte)
    $stmtSub = $pdo->prepare("
        DELETE FROM submissions
        WHERE session_id IN (
            SELECT id FROM sessions
            WHERE created_by_admin_id = ?
            AND id != ?
        )
    ");
    $stmtSub->execute([TESTER_ADMIN_ID, PROTECTED_SESSION_ID]);
    $deletedSubmissions = $stmtSub->rowCount();

    // 2. Tester-Sessions löschen (außer geschützte)
    $stmtSes = $pdo->prepare("
        DELETE FROM sessions
        WHERE created_by_admin_id = ?
        AND id != ?
    ");
    $stmtSes->execute([TESTER_ADMIN_ID, PROTECTED_SESSION_ID]);
    $deletedSessions = $stmtSes->rowCount();

    $pdo->commit();

    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] Cleanup: {$deletedSessions} Sessions, {$deletedSubmissions} Submissions gelöscht.\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] FEHLER: " . $e->getMessage() . "\n";
    exit(1);
}
