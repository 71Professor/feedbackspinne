<?php
/**
 * Feedbackspinne – Auth REST-API
 *
 * Endpoints (POST, JSON body):
 *   register          – Neuen Account anlegen
 *   login             – Einloggen
 *   logout            – Session beenden
 *   forgot_password   – Reset-Link anfordern
 *   reset_password    – Passwort per Token zurücksetzen
 *   change_password   – Passwort ändern (eingeloggt)
 *   me                – Eigene Profildaten abrufen (GET)
 *
 * Alle Responses: JSON { success: bool, message: string, [data: mixed] }
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/mailer.php';

// ── Security-Header + JSON-Response-Format ──────────────────────────────────
setSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

define('USER_SESSION_NAME', 'fs_user');
define('USER_SESSION_TIMEOUT', 1800); // 30 Minuten

// ── Router ───────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? '');

// Nur GET für /me, alles andere POST
if ($method === 'GET' && $action === 'me') {
    handleMe();
} elseif ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ($action) {
        case 'register':        handleRegister($body);       break;
        case 'login':           handleLogin($body);           break;
        case 'logout':          handleLogout();               break;
        case 'forgot_password': handleForgotPassword($body); break;
        case 'reset_password':  handleResetPassword($body);  break;
        case 'change_password': handleChangePassword($body); break;
        default:
            apiError('Unbekannter Endpoint.', 404);
    }
} else {
    apiError('Methode nicht erlaubt.', 405);
}

// ════════════════════════════════════════════════════════════════════════════
// HANDLER
// ════════════════════════════════════════════════════════════════════════════

// ── REGISTER ─────────────────────────────────────────────────────────────────
function handleRegister(array $body): void {
    // Rate-Limit: 5 Registrierungen pro IP pro 5 Minuten
    if (!checkRateLimit('auth_register', 5, 300)) {
        logSecurityEvent('register_rate_limit', 'Rate limit exceeded');
        apiError('Zu viele Anfragen. Bitte warte kurz.', 429);
    }

    $username = trim($body['username'] ?? '');
    $email    = trim($body['email']    ?? '');
    $password = $body['password']      ?? '';

    // Validierung
    $errors = [];
    if (!preg_match('/^[a-zA-Z0-9_\-]{3,50}$/', $username)) {
        $errors[] = 'Benutzername: 3–50 Zeichen, nur Buchstaben, Zahlen, _ und -.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
        $errors[] = 'Ungültige E-Mail-Adresse.';
    }
    $pwError = validatePasswordComplexity($password);
    if ($pwError) {
        $errors[] = $pwError;
    }

    if ($errors) {
        apiError(implode(' ', $errors), 422);
    }

    $pdo = getDB();

    // Duplicate-Check (timing-safe: beide Felder prüfen, keine Info leaken)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        incrementRateLimit('auth_register');
        logSecurityEvent('register_duplicate', "username={$username} email={$email}");
        // Bewusst unspezifisch: kein Hinweis welches Feld belegt ist
        apiError('Registrierung fehlgeschlagen. Benutzername oder E-Mail bereits vergeben.', 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password_hash)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$username, $email, $hash]);
    $userId = (int)$pdo->lastInsertId();

    logSecurityEvent('register_success', "username={$username}", $userId);
    apiSuccess('Registrierung erfolgreich. Du kannst dich jetzt einloggen.');
}

// ── LOGIN ─────────────────────────────────────────────────────────────────────
function handleLogin(array $body): void {
    // Rate-Limit: 5 Versuche pro 5 Minuten (IP-basiert)
    if (!checkRateLimit('auth_login', 5, 300)) {
        $remaining = getRateLimitTimeRemaining('auth_login');
        logSecurityEvent('login_rate_limit', 'Rate limit exceeded');
        apiError('Zu viele fehlgeschlagene Versuche. Warte ' . ceil($remaining / 60) . ' Minute(n).', 429);
    }

    $identifier = trim($body['identifier'] ?? ''); // username oder e-mail
    $password   = $body['password']        ?? '';

    if (!$identifier || !$password) {
        apiError('Bitte alle Felder ausfüllen.', 422);
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT * FROM users
        WHERE (username = ? OR email = ?) AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    // Timing-sicherer Vergleich: immer password_verify aufrufen
    $dummyHash = '$2y$12$invaliddummyhashfortimingatk.xxxxxxxxxxxxxxxxxxxxxxxxxx';
    $valid = $user && password_verify($password, $user['password_hash'] ?? $dummyHash);

    if (!$valid) {
        incrementRateLimit('auth_login');
        logSecurityEvent('login_fail', "identifier={$identifier}");
        apiError('Ungültige Anmeldedaten.', 401);
    }

    // Erfolg
    resetRateLimit('auth_login');

    // Session regenerieren (Schutz gegen Session-Fixation)
    session_regenerate_id(true);
    $_SESSION[USER_SESSION_NAME] = true;
    $_SESSION['user_id']         = $user['id'];
    $_SESSION['user_username']   = $user['username'];
    $_SESSION['user_email']      = $user['email'];
    $_SESSION['last_activity']   = time();

    logSecurityEvent('login_success', "username={$user['username']}", (int)$user['id']);
    apiSuccess('Login erfolgreich.', [
        'user' => [
            'id'       => $user['id'],
            'username' => $user['username'],
            'email'    => $user['email'],
        ]
    ]);
}

// ── LOGOUT ───────────────────────────────────────────────────────────────────
function handleLogout(): void {
    $userId = $_SESSION['user_id'] ?? null;
    logSecurityEvent('logout', '', $userId);

    session_unset();
    session_destroy();
    apiSuccess('Erfolgreich abgemeldet.');
}

// ── FORGOT PASSWORD ──────────────────────────────────────────────────────────
function handleForgotPassword(array $body): void {
    // Rate-Limit: 3 Anfragen pro IP pro 15 Minuten
    if (!checkRateLimit('auth_forgot', 3, 900)) {
        logSecurityEvent('forgot_rate_limit', 'Rate limit exceeded');
        // Gleiche Antwort wie Erfolg (Anti-Enumeration)
        apiSuccess('Falls ein Konto mit dieser E-Mail existiert, wurde eine E-Mail gesendet.');
    }

    $email = trim($body['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Gleiche Antwort – kein Hinweis ob E-Mail gültig
        apiSuccess('Falls ein Konto mit dieser E-Mail existiert, wurde eine E-Mail gesendet.');
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Alte, ungenutzte Tokens invalidieren
        $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL")
            ->execute([$user['id']]);

        // Sicheres Token erzeugen
        $rawToken  = bin2hex(random_bytes(32)); // 64 hex chars
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 60 Minuten

        $pdo->prepare("
            INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
            VALUES (?, ?, ?)
        ")->execute([$user['id'], $tokenHash, $expiresAt]);

        sendPasswordResetEmail($email, $user['username'], $rawToken);
        logSecurityEvent('forgot_password', "email={$email}", (int)$user['id']);
    }

    // Immer gleiche Antwort (Anti-E-Mail-Enumeration)
    apiSuccess('Falls ein Konto mit dieser E-Mail existiert, wurde eine E-Mail gesendet.');
}

// ── RESET PASSWORD ───────────────────────────────────────────────────────────
function handleResetPassword(array $body): void {
    if (!checkRateLimit('auth_reset', 10, 300)) {
        apiError('Zu viele Anfragen. Bitte warte kurz.', 429);
    }

    $rawToken   = trim($body['token']    ?? '');
    $newPassword = $body['password']     ?? '';

    if (!$rawToken) {
        apiError('Ungültiger oder abgelaufener Token.', 422);
    }

    $pwError = validatePasswordComplexity($newPassword);
    if ($pwError) {
        apiError($pwError, 422);
    }

    $tokenHash = hash('sha256', $rawToken);
    $pdo       = getDB();

    $stmt = $pdo->prepare("
        SELECT prt.*, u.username
        FROM   password_reset_tokens prt
        JOIN   users u ON u.id = prt.user_id
        WHERE  prt.token_hash = ?
          AND  prt.used_at   IS NULL
          AND  prt.expires_at  > NOW()
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $record = $stmt->fetch();

    if (!$record) {
        logSecurityEvent('reset_invalid_token', "hash={$tokenHash}");
        apiError('Ungültiger oder abgelaufener Reset-Link.', 400);
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    // Token als benutzt markieren + Passwort updaten (transaktion)
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?")
        ->execute([$record['id']]);
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
        ->execute([$hash, $record['user_id']]);
    $pdo->commit();

    logSecurityEvent('reset_password_success', "user_id={$record['user_id']}", (int)$record['user_id']);
    apiSuccess('Passwort erfolgreich zurückgesetzt. Du kannst dich jetzt einloggen.');
}

// ── CHANGE PASSWORD ──────────────────────────────────────────────────────────
function handleChangePassword(array $body): void {
    requireUserSession();

    $userId      = (int)$_SESSION['user_id'];
    $oldPassword = $body['old_password'] ?? '';
    $newPassword = $body['new_password'] ?? '';

    if (!$oldPassword || !$newPassword) {
        apiError('Bitte alle Felder ausfüllen.', 422);
    }

    $pwError = validatePasswordComplexity($newPassword);
    if ($pwError) {
        apiError($pwError, 422);
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($oldPassword, $user['password_hash'])) {
        logSecurityEvent('change_password_fail', 'wrong old password', $userId);
        apiError('Aktuelles Passwort ist falsch.', 401);
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
        ->execute([$hash, $userId]);

    logSecurityEvent('change_password_success', '', $userId);
    apiSuccess('Passwort erfolgreich geändert.');
}

// ── ME ────────────────────────────────────────────────────────────────────────
function handleMe(): void {
    checkSessionTimeout();
    if (!isUserLoggedIn()) {
        apiError('Nicht eingeloggt.', 401);
    }
    apiSuccess('OK', [
        'user' => [
            'id'       => $_SESSION['user_id'],
            'username' => $_SESSION['user_username'],
            'email'    => $_SESSION['user_email'],
        ]
    ]);
}

// ════════════════════════════════════════════════════════════════════════════
// HELPER
// ════════════════════════════════════════════════════════════════════════════

function isUserLoggedIn(): bool {
    return isset($_SESSION[USER_SESSION_NAME]) && $_SESSION[USER_SESSION_NAME] === true;
}

function checkSessionTimeout(): void {
    if (!isUserLoggedIn()) return;
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > USER_SESSION_TIMEOUT) {
            $userId = $_SESSION['user_id'] ?? null;
            logSecurityEvent('session_timeout', '', $userId);
            session_unset();
            session_destroy();
            return;
        }
    }
    $_SESSION['last_activity'] = time();
}

function requireUserSession(): void {
    checkSessionTimeout();
    if (!isUserLoggedIn()) {
        apiError('Nicht eingeloggt.', 401);
    }
}

/**
 * Validate password complexity.
 * Returns error string or null on success.
 */
function validatePasswordComplexity(string $password): ?string {
    if (mb_strlen($password) < 8) {
        return 'Passwort muss mindestens 8 Zeichen lang sein.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Passwort muss mindestens einen Großbuchstaben enthalten.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Passwort muss mindestens einen Kleinbuchstaben enthalten.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Passwort muss mindestens eine Ziffer enthalten.';
    }
    if (!preg_match('/[\W_]/', $password)) {
        return 'Passwort muss mindestens ein Sonderzeichen enthalten.';
    }
    return null;
}

function apiSuccess(string $message, $data = null): void {
    $resp = ['success' => true, 'message' => $message];
    if ($data !== null) $resp['data'] = $data;
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    exit;
}

function apiError(string $message, int $status = 400): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
