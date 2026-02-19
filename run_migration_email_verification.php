<?php
/**
 * Datenbankmigrierung: E-Mail-Verifikation
 *
 * Führt folgende Schemaänderungen durch:
 *   1. Tabelle email_verification_tokens anlegen
 *   2. Spalten is_email_verified + email_verified_at in users ergänzen
 *
 * Nur einmalig ausführen:
 *   php run_migration_email_verification.php
 */

require_once __DIR__ . '/config.php';

try {
    $pdo = getDB();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ------------------------------------------------------------------
    // 1. email_verification_tokens
    // ------------------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_verification_tokens (
            id      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED  NOT NULL,
            token   VARCHAR(64)   NOT NULL,
            created DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires DATETIME      NOT NULL,
            used    TINYINT(1)    NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY idx_token   (token),
            KEY        idx_expires (expires),
            KEY        idx_user_id (user_id),
            CONSTRAINT fk_evt_user
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Tabelle 'email_verification_tokens' bereit.\n";

    // ------------------------------------------------------------------
    // 2. users – Verifikationsspalten (idempotent)
    // ------------------------------------------------------------------
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_email_verified'")->fetch();
    if (!$col) {
        $pdo->exec("
            ALTER TABLE users
                ADD COLUMN is_email_verified TINYINT(1) NOT NULL DEFAULT 0    AFTER is_active,
                ADD COLUMN email_verified_at DATETIME        NULL DEFAULT NULL AFTER is_email_verified,
                ADD INDEX  idx_email_verified (is_email_verified)
        ");
        echo "✓ Spalten 'is_email_verified' + 'email_verified_at' in 'users' ergänzt.\n";

        // Bestehende User als verifiziert markieren, damit sie sich weiterhin einloggen können
        $updated = $pdo->exec("UPDATE users SET is_email_verified = 1, email_verified_at = NOW() WHERE is_email_verified = 0");
        echo "✓ {$updated} bestehende(r) User als verifiziert markiert (Bestandsschutz).\n";
    } else {
        echo "✓ Spalten bereits vorhanden – übersprungen.\n";
    }

    echo "\nMigrierung abgeschlossen.\n";

} catch (PDOException $e) {
    echo "✗ Migrierung fehlgeschlagen:\n  " . $e->getMessage() . "\n";
    exit(1);
}
