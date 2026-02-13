-- Migration: Add rate_limits table for session-independent rate limiting
-- Fixes Security Audit Issue #3: Session-based rate limiting bypass
-- Author: Security Audit Fix
-- Date: 2026-02-13

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Composite key for rate limit tracking
    limit_key VARCHAR(100) NOT NULL COMMENT 'Rate limit type (admin_login, session_code)',
    client_identifier VARCHAR(255) NOT NULL COMMENT 'IP address with optional context hash',

    -- Rate limit counters
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    first_attempt_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL DEFAULT NULL,

    -- Metadata for monitoring and cleanup
    last_attempt_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Composite unique index for fast lookups and atomicity
    UNIQUE KEY idx_rate_limit_lookup (limit_key, client_identifier),

    -- Indexes for cleanup queries
    KEY idx_blocked_until (blocked_until),
    KEY idx_last_attempt (last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
