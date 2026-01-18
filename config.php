<?php
/**
 * Datenbank-Konfiguration
 * Passe diese Werte an deine all-inkl.com Datenbank an
 */

// Datenbank-Verbindungseinstellungen
define('DB_HOST', 'localhost'); // meist 'localhost' bei all-inkl
define('DB_NAME', 'd045e8fc'); // Name deiner MySQL-Datenbank
define('DB_USER', 'd045e8fc'); // MySQL-Benutzername
define('DB_PASS', 'p9TPcEDsnMsvLaUVMGqp'); // MySQL-Passwort
define('DB_CHARSET', 'utf8mb4');

// Admin-Einstellungen
define('ADMIN_SESSION_NAME', 'netzdiagramm_admin');
define('SESSION_TIMEOUT', 3600); // 1 Stunde

// Sicherheit
define('SECURE_KEY', '6g4uJ$bCA^o)nZb;!>6-H=yYbFA(QH[-'); // Ändere dies zu einem zufälligen String

// Fehlerberichterstattung (auf false in Produktion)
define('DEBUG_MODE', true);

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
