<?php
/**
 * Feedbackspinne - Security Event Logger
 *
 * Logs security-relevant events (login attempts, password resets, etc.)
 * to the database for auditing and incident response.
 */

require_once __DIR__ . '/../config.php';

/**
 * Log a security event to the database.
 *
 * @param string $event     Event type (e.g. 'login_success', 'login_fail')
 * @param string $details   Human-readable detail message
 * @param int|null $userId  Associated user ID (null for unauthenticated events)
 */
function logSecurityEvent(string $event, string $details = '', ?int $userId = null): void {
    try {
        $pdo = getDB();
        $ip  = getClientIP();
        $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);

        $stmt = $pdo->prepare("
            INSERT INTO security_log (event_type, user_id, ip_address, user_agent, details, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$event, $userId, $ip, $ua, $details]);
    } catch (PDOException $e) {
        // Never let logging break the application
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log('Security log failed: ' . $e->getMessage());
        }
    }
}

/**
 * Retrieve recent security log entries (admin use).
 *
 * @param int $limit  Max rows to return
 * @return array
 */
function getSecurityLog(int $limit = 100): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT sl.*, u.username
        FROM   security_log sl
        LEFT JOIN users u ON u.id = sl.user_id
        ORDER BY sl.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}
