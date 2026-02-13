<?php
/**
 * Rate Limits Cleanup Job
 *
 * Removes old rate limit records to prevent table bloat.
 * Deletes records that are older than 7 days and no longer actively blocked.
 *
 * Usage via Cron:
 *   # Run cleanup daily at 2 AM
 *   0 2 * * * php /path/to/feedbackspinne/cron_cleanup_rate_limits.php
 *
 * Usage via Command Line:
 *   php cron_cleanup_rate_limits.php
 */

require_once __DIR__ . '/config.php';

try {
    $pdo = getDB();

    // Delete records older than 7 days with no active blocks
    $stmt = $pdo->prepare("
        DELETE FROM rate_limits
        WHERE last_attempt_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND (blocked_until IS NULL OR blocked_until < NOW())
    ");
    $stmt->execute();

    $deleted = $stmt->rowCount();

    // Output result
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] Rate limit cleanup completed: {$deleted} old records removed.\n";

    // Log to syslog if available (optional)
    if (function_exists('syslog')) {
        syslog(LOG_INFO, "Rate limit cleanup: {$deleted} records removed");
    }

} catch (PDOException $e) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] ERROR: Cleanup failed - " . $e->getMessage() . "\n";

    // Log error to syslog if available
    if (function_exists('syslog')) {
        syslog(LOG_ERR, "Rate limit cleanup failed: " . $e->getMessage());
    }

    exit(1);
}
