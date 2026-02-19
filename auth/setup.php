<?php
/**
 * Feedbackspinne - Auth-Datenbank-Setup
 *
 * Erstellt alle für das Auth-System benötigten Tabellen.
 * Dieses Skript nur einmalig ausführen (oder nach Änderungen am Schema).
 *
 * Aufruf: php auth/setup.php   oder per Browser (nur mit Admin-Zugriff)
 */

// Nur CLI oder lokale Ausführung erlauben
if (PHP_SAPI !== 'cli' && ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(403);
    exit('Setup nur über CLI oder lokal erlaubt.');
}

require_once __DIR__ . '/../config.php';

$pdo = getDB();

// ---------------------------------------------------------------------------
// 1. users
// ---------------------------------------------------------------------------
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
        username      VARCHAR(50)     NOT NULL,
        email         VARCHAR(255)    NOT NULL,
        password_hash VARCHAR(255)    NOT NULL,
        is_active     TINYINT(1)      NOT NULL DEFAULT 1,
        created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_username (username),
        UNIQUE KEY uq_email    (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "✓ Tabelle 'users' bereit.\n";

// ---------------------------------------------------------------------------
// 2. password_reset_tokens
// ---------------------------------------------------------------------------
$pdo->exec("
    CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id    INT UNSIGNED NOT NULL,
        token_hash VARCHAR(64)  NOT NULL COMMENT 'SHA-256 of the raw token',
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME     NOT NULL,
        used_at    DATETIME         NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_token (token_hash),
        KEY        fk_prt_user (user_id),
        CONSTRAINT fk_prt_user
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "✓ Tabelle 'password_reset_tokens' bereit.\n";

// ---------------------------------------------------------------------------
// 3. security_log
// ---------------------------------------------------------------------------
$pdo->exec("
    CREATE TABLE IF NOT EXISTS security_log (
        id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        event_type VARCHAR(50)     NOT NULL,
        user_id    INT UNSIGNED        NULL DEFAULT NULL,
        ip_address VARCHAR(45)     NOT NULL,
        user_agent VARCHAR(255)    NOT NULL,
        details    TEXT                NULL,
        created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_event    (event_type),
        KEY idx_user     (user_id),
        KEY idx_ip       (ip_address),
        KEY idx_created  (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "✓ Tabelle 'security_log' bereit.\n";

// ---------------------------------------------------------------------------
// 4. rate_limits (ggf. bereits vorhanden – ADD COLUMN falls nötig)
// ---------------------------------------------------------------------------
// Die Haupttabelle rate_limits wurde in der Haupt-App angelegt.
// Prüfen, ob sie existiert; falls nicht, hier ebenfalls anlegen.
$tables = $pdo->query("SHOW TABLES LIKE 'rate_limits'")->fetchAll();
if (empty($tables)) {
    $pdo->exec("
        CREATE TABLE rate_limits (
            id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            limit_key         VARCHAR(50)   NOT NULL,
            client_identifier VARCHAR(200)  NOT NULL,
            attempt_count     INT           NOT NULL DEFAULT 0,
            first_attempt_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_attempt_at   DATETIME          NULL DEFAULT NULL,
            blocked_until     DATETIME          NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_key_client (limit_key, client_identifier),
            KEY idx_blocked (blocked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Tabelle 'rate_limits' angelegt.\n";
} else {
    echo "✓ Tabelle 'rate_limits' bereits vorhanden.\n";
}

echo "\nSetup abgeschlossen. Alle Tabellen sind bereit.\n";
