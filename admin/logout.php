<?php
require_once '../config.php';
setSecurityHeaders();

/**
 * Vollständiger, sicherer Logout
 *
 * Schritte für sicheren Logout:
 * 1. Session-Variablen löschen
 * 2. Session-Cookie löschen
 * 3. Session zerstören
 */

// 1. Alle Session-Variablen löschen
$_SESSION = array();

// 2. Session-Cookie löschen (wichtig für vollständigen Logout)
if (isset($_COOKIE[session_name()])) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000, // Cookie in der Vergangenheit setzen (löschen)
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Session zerstören
session_destroy();

// Zurück zur Startseite mit Bestätigungsmeldung
header('Location: ../index.php?logged_out=1');
exit;
