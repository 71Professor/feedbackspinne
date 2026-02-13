<?php
/**
 * Database Migration: Create rate_limits table
 *
 * This migration creates the rate_limits table for session-independent rate limiting.
 * Run this script once to upgrade the database schema.
 *
 * Usage:
 *   php run_migration_rate_limits.php
 *   OR access via web browser: http://yoursite.com/run_migration_rate_limits.php
 */

require_once 'config.php';

try {
    $pdo = getDB();

    // Check if table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'rate_limits'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Migration already completed. Table 'rate_limits' exists.\n";
        exit(0);
    }

    // Run migration SQL
    $sql = file_get_contents(__DIR__ . '/migration_add_rate_limits.sql');

    if ($sql === false) {
        throw new Exception("Could not read migration_add_rate_limits.sql file");
    }

    $pdo->exec($sql);

    echo "✓ Migration successful!\n";
    echo "  - Table 'rate_limits' created\n";
    echo "  - Indexes created for optimal performance\n";
    echo "  - Rate limiting is now session-independent and cannot be bypassed\n";

} catch (PDOException $e) {
    echo "✗ Migration failed (Database Error):\n";
    echo "  " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Migration failed:\n";
    echo "  " . $e->getMessage() . "\n";
    exit(1);
}
