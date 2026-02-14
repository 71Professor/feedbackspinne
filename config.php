<?php
/**
 * Feedbackspinne - Zentrale Konfiguration
 * Credentials werden aus .env-Datei geladen (NICHT versioniert!)
 */

// .env-Datei laden (einfacher Parser ohne externe Dependencies)
function loadEnv($path) {
    if (!file_exists($path)) {
        die('.env-Datei nicht gefunden! Bitte .env.example zu .env kopieren und anpassen.');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Kommentare und leere Zeilen ignorieren
        if (strpos(trim($line), '#') === 0 || trim($line) === '') {
            continue;
        }

        // KEY=VALUE parsen
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Umgebungsvariable setzen falls noch nicht vorhanden
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// .env laden
loadEnv(__DIR__ . '/.env');

// Datenbank-Verbindungseinstellungen (aus .env)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Validierung: Prüfen ob alle DB-Credentials gesetzt sind
if (empty(DB_NAME) || empty(DB_USER) || empty(DB_PASS)) {
    die('Fehler: Datenbank-Credentials fehlen in .env-Datei!');
}

// Admin-Einstellungen
define('ADMIN_SESSION_NAME', 'netzdiagramm_admin');
define('SESSION_TIMEOUT', 3600); // 1 Stunde

// Sicherheit (aus .env)
define('SECURE_KEY', getenv('SECURE_KEY') ?: '');
if (empty(SECURE_KEY)) {
    die('Fehler: SECURE_KEY fehlt in .env-Datei!');
}

// Fehlerberichterstattung (aus .env, default: false)
$debugMode = getenv('DEBUG_MODE');
define('DEBUG_MODE', filter_var($debugMode, FILTER_VALIDATE_BOOLEAN));

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Datenbankverbindung herstellen
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());
            } else {
                die('Datenbankverbindung fehlgeschlagen. Bitte kontaktiere den Administrator.');
            }
        }
    }
    
    return $pdo;
}

// Session-Sicherheitsparameter setzen (vor session_start)
// HttpOnly: Session-Cookie nur über HTTP(S) zugreifbar, nicht über JavaScript
ini_set('session.cookie_httponly', '1');

// Secure: Cookie nur über HTTPS senden (wenn HTTPS aktiv)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
           || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
ini_set('session.cookie_secure', $isHttps ? '1' : '0');

// SameSite: Schutz gegen CSRF-Angriffe über Cross-Site-Requests
ini_set('session.cookie_samesite', 'Lax');

// Strict Mode: Nur serverseitig erzeugte Session-IDs akzeptieren
ini_set('session.use_strict_mode', '1');

// Session starten
session_start();

// CSRF-Token generieren
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF-Token validieren
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get real client IP address, considering proxies and load balancers
 *
 * Checks multiple headers in order of reliability to determine the true client IP.
 * This helps prevent rate limit bypasses via proxy header manipulation.
 *
 * @return string Client IP address
 */
function getClientIP() {
    // Check for proxy headers in order of reliability
    $headers = [
        'HTTP_CF_CONNECTING_IP',    // Cloudflare
        'HTTP_X_FORWARDED_FOR',     // Standard proxy header
        'HTTP_X_REAL_IP',           // Nginx proxy
        'REMOTE_ADDR'               // Direct connection
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];

            // X-Forwarded-For can contain multiple IPs: "client, proxy1, proxy2"
            // Take the first (leftmost) IP as the original client
            if ($header === 'HTTP_X_FORWARDED_FOR') {
                $ips = array_map('trim', explode(',', $ip));
                $ip = $ips[0];
            }

            // Validate IP address format and filter out private/reserved ranges
            // to prevent header spoofing attacks
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    // Fallback to REMOTE_ADDR (always available)
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Rate Limiting - Schutz gegen Brute-Force-Angriffe
 *
 * Database-backed rate limiting to prevent session-based bypasses.
 * Uses client IP address (+ username for admin login) as identifier.
 *
 * @param string $key Eindeutiger Key (z.B. 'admin_login', 'session_code')
 * @param int $maxAttempts Maximale Versuche im Zeitfenster (default: 5)
 * @param int $timeWindow Zeitfenster in Sekunden (default: 900 = 15 Min)
 * @return bool True wenn erlaubt, False wenn Rate Limit überschritten
 */
function checkRateLimit($key, $maxAttempts = 5, $timeWindow = 900) {
    $pdo = getDB();
    $clientIP = getClientIP();

    // Build client identifier - for admin login, include username hash
    $identifier = $clientIP;
    if ($key === 'admin_login' && isset($_POST['username'])) {
        // Hash username to avoid storing PII directly, but maintain per-user limits
        $identifier = $clientIP . '_' . md5($_POST['username']);
    }

    try {
        // Start transaction for atomic read-modify-write
        $pdo->beginTransaction();

        // Get or create rate limit record with row-level lock
        $stmt = $pdo->prepare("
            SELECT * FROM rate_limits
            WHERE limit_key = ? AND client_identifier = ?
            FOR UPDATE
        ");
        $stmt->execute([$key, $identifier]);
        $record = $stmt->fetch();

        $now = time();

        if (!$record) {
            // First attempt - create record
            $stmt = $pdo->prepare("
                INSERT INTO rate_limits
                (limit_key, client_identifier, attempt_count, first_attempt_at)
                VALUES (?, ?, 0, FROM_UNIXTIME(?))
            ");
            $stmt->execute([$key, $identifier, $now]);
            $pdo->commit();
            return true;
        }

        // Convert timestamps to Unix time for calculations
        $firstAttempt = strtotime($record['first_attempt_at']);
        $blockedUntil = $record['blocked_until'] ? strtotime($record['blocked_until']) : null;

        // Check if currently blocked
        if ($blockedUntil && $now < $blockedUntil) {
            $pdo->commit();
            return false;
        }

        // Check if time window has expired - reset if so
        if ($now - $firstAttempt > $timeWindow) {
            $stmt = $pdo->prepare("
                UPDATE rate_limits
                SET attempt_count = 0,
                    first_attempt_at = FROM_UNIXTIME(?),
                    blocked_until = NULL,
                    last_attempt_at = FROM_UNIXTIME(?)
                WHERE limit_key = ? AND client_identifier = ?
            ");
            $stmt->execute([$now, $now, $key, $identifier]);
            $pdo->commit();
            return true;
        }

        // Check if limit reached
        if ($record['attempt_count'] >= $maxAttempts) {
            // Progressive blocking: longer blocks for repeated violations
            $blockMultiplier = 1 + floor($record['attempt_count'] / $maxAttempts);
            $blockDuration = min(3600, $timeWindow * $blockMultiplier); // Max 1 hour
            $blockedUntil = $now + $blockDuration;

            $stmt = $pdo->prepare("
                UPDATE rate_limits
                SET blocked_until = FROM_UNIXTIME(?),
                    last_attempt_at = FROM_UNIXTIME(?)
                WHERE limit_key = ? AND client_identifier = ?
            ");
            $stmt->execute([$blockedUntil, $now, $key, $identifier]);
            $pdo->commit();
            return false;
        }

        // Update last attempt time
        $stmt = $pdo->prepare("
            UPDATE rate_limits
            SET last_attempt_at = FROM_UNIXTIME(?)
            WHERE limit_key = ? AND client_identifier = ?
        ");
        $stmt->execute([$now, $key, $identifier]);

        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Log error if in debug mode
        if (DEBUG_MODE) {
            error_log("Rate limit check failed: " . $e->getMessage());
        }

        // Fail-open: allow request if rate limiting system fails
        // Alternative: fail-closed by returning false
        return true;
    }
}

/**
 * Rate Limit Counter erhöhen (nach fehlgeschlagenem Versuch)
 *
 * Increments the attempt counter in the database after a failed attempt.
 *
 * @param string $key Rate limit key (e.g., 'admin_login', 'session_code')
 */
function incrementRateLimit($key) {
    $pdo = getDB();
    $clientIP = getClientIP();

    // Build client identifier - same logic as checkRateLimit()
    $identifier = $clientIP;
    if ($key === 'admin_login' && isset($_POST['username'])) {
        $identifier = $clientIP . '_' . md5($_POST['username']);
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE rate_limits
            SET attempt_count = attempt_count + 1,
                last_attempt_at = CURRENT_TIMESTAMP
            WHERE limit_key = ? AND client_identifier = ?
        ");
        $stmt->execute([$key, $identifier]);
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            error_log("Rate limit increment failed: " . $e->getMessage());
        }
    }
}

/**
 * Rate Limit zurücksetzen (nach erfolgreichem Versuch)
 *
 * Deletes the rate limit record from the database after a successful attempt.
 *
 * @param string $key Rate limit key (e.g., 'admin_login', 'session_code')
 */
function resetRateLimit($key) {
    $pdo = getDB();
    $clientIP = getClientIP();

    // Build client identifier - same logic as checkRateLimit()
    $identifier = $clientIP;
    if ($key === 'admin_login' && isset($_POST['username'])) {
        $identifier = $clientIP . '_' . md5($_POST['username']);
    }

    try {
        $stmt = $pdo->prepare("
            DELETE FROM rate_limits
            WHERE limit_key = ? AND client_identifier = ?
        ");
        $stmt->execute([$key, $identifier]);
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            error_log("Rate limit reset failed: " . $e->getMessage());
        }
    }
}

/**
 * Verbleibende Zeit bis Rate Limit zurückgesetzt wird
 *
 * Queries the database for the blocked_until timestamp and calculates remaining time.
 *
 * @param string $key Rate limit key (e.g., 'admin_login', 'session_code')
 * @return int Sekunden bis Reset (0 wenn nicht geblockt)
 */
function getRateLimitTimeRemaining($key) {
    $pdo = getDB();
    $clientIP = getClientIP();

    // Build client identifier - same logic as checkRateLimit()
    $identifier = $clientIP;
    if ($key === 'admin_login' && isset($_POST['username'])) {
        $identifier = $clientIP . '_' . md5($_POST['username']);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT blocked_until
            FROM rate_limits
            WHERE limit_key = ? AND client_identifier = ?
        ");
        $stmt->execute([$key, $identifier]);
        $record = $stmt->fetch();

        if ($record && $record['blocked_until']) {
            $blockedUntil = strtotime($record['blocked_until']);
            $remaining = $blockedUntil - time();
            return max(0, $remaining);
        }

        return 0;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            error_log("Rate limit time check failed: " . $e->getMessage());
        }
        return 0;
    }
}

// 4-stelligen Code generieren
function generateSessionCode() {
    $pdo = getDB();
    do {
        $code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT id FROM sessions WHERE code = ?");
        $stmt->execute([$code]);
    } while ($stmt->rowCount() > 0); // Wiederhole, falls Code bereits existiert

    return $code;
}

/**
 * Validate and sanitize chart color to prevent CSS injection
 *
 * Ensures the color is a valid hex color format (#RRGGBB) to prevent
 * CSS/HTML context breakouts and injection attacks.
 *
 * @param string $color The color value to validate (e.g., from user input or database)
 * @param string $default The default color to use if validation fails (default: '#7ab800')
 * @return string A safe hex color value
 */
function sanitizeChartColor($color, $default = '#7ab800') {
    // Strict hex color format validation: #RRGGBB (6 hex digits)
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        return $color;
    }

    // Return safe default if validation fails
    return $default;
}

// JSON-Response senden
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Admin-Login prüfen
function isAdminLoggedIn() {
    return isset($_SESSION[ADMIN_SESSION_NAME]) && $_SESSION[ADMIN_SESSION_NAME] === true;
}

// Admin-Zugriff erzwingen
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: /admin/index.php');
        exit;
    }

    // Session-Timeout prüfen
    if (isset($_SESSION['last_activity'])) {
        $inactiveTime = time() - $_SESSION['last_activity'];

        if ($inactiveTime > SESSION_TIMEOUT) {
            // Session abgelaufen: Ausloggen und zur Login-Seite weiterleiten
            session_unset();
            session_destroy();
            header('Location: /admin/index.php');
            exit;
        }
    }

    // Aktivitätszeitpunkt aktualisieren
    $_SESSION['last_activity'] = time();
}

/**
 * Set application-level security headers
 *
 * Implements defense-in-depth security headers to protect against various attacks:
 * - Content-Security-Policy: Whitelists allowed sources for scripts, styles, etc.
 * - X-Content-Type-Options: Prevents MIME-sniffing attacks
 * - X-Frame-Options: Prevents clickjacking attacks
 * - Referrer-Policy: Controls referrer information leakage
 * - Permissions-Policy: Restricts browser features
 *
 * Call this function early in each entry point (before any output).
 */
function setSecurityHeaders() {
    // Detect if HTTPS is active
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
               || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    // Content Security Policy
    // Whitelist CDN sources for scripts and allow inline styles (required for dynamic theming)
    $csp = [
        "default-src 'self'",
        "script-src 'self' https://cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline'", // unsafe-inline needed for dynamic color theming
        "img-src 'self' data:", // data: needed for chart export
        "font-src 'self'",
        "connect-src 'self'",
        "frame-ancestors 'none'", // Equivalent to X-Frame-Options: DENY
        "base-uri 'self'",
        "form-action 'self'"
    ];

    // Add upgrade-insecure-requests only on HTTPS
    if ($isHttps) {
        $csp[] = "upgrade-insecure-requests";
    }

    header("Content-Security-Policy: " . implode('; ', $csp));

    // Prevent MIME-sniffing attacks
    header("X-Content-Type-Options: nosniff");

    // Prevent clickjacking (redundant with CSP frame-ancestors, but provides defense-in-depth)
    header("X-Frame-Options: DENY");

    // Control referrer information leakage
    header("Referrer-Policy: strict-origin-when-cross-origin");

    // Restrict browser features to minimum required
    header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");
}
