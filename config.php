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
}
