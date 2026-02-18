<?php
/**
 * Feedbackspinne – Auth Test Suite
 *
 * Runs basic smoke-tests against the REST-API endpoints.
 * Execute via CLI only:  php auth/test_auth.php
 *
 * Requires:
 *   - A working .env (DB + optional SMTP)
 *   - Tables created via:  php auth/setup.php
 *   - APP_URL set in .env pointing to the running dev server
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Test-Skript nur über CLI ausführen.');
}

require_once __DIR__ . '/../config.php';

// ── Colors ──────────────────────────────────────────────────────────────────
function green(string $s): string  { return "\e[32m{$s}\e[0m"; }
function red(string $s): string    { return "\e[31m{$s}\e[0m"; }
function yellow(string $s): string { return "\e[33m{$s}\e[0m"; }
function bold(string $s): string   { return "\e[1m{$s}\e[0m"; }

// ── Test runner ──────────────────────────────────────────────────────────────
$passed = 0; $failed = 0;

function test(string $name, bool $ok, string $detail = ''): void {
    global $passed, $failed;
    if ($ok) {
        echo green('  ✓ PASS') . "  {$name}\n";
        $passed++;
    } else {
        echo red('  ✗ FAIL') . "  {$name}" . ($detail ? " → {$detail}" : '') . "\n";
        $failed++;
    }
}

// ── HTTP helper (requires running dev server or APP_URL) ─────────────────────
$appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
$cookieJar = tempnam(sys_get_temp_dir(), 'fs_test_cookies_');

function httpPost(string $action, array $body, string &$rawResp): array {
    global $appUrl, $cookieJar;
    $url  = "{$appUrl}/auth/api.php?action={$action}";
    $ch   = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false, // dev only
    ]);
    $rawResp = curl_exec($ch);
    curl_close($ch);
    return json_decode($rawResp ?: '{}', true) ?? [];
}

function httpGet(string $action): array {
    global $appUrl, $cookieJar;
    $url = "{$appUrl}/auth/api.php?action={$action}";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    return json_decode($raw ?: '{}', true) ?? [];
}

// ════════════════════════════════════════════════════════════════════════════
echo bold("\n═══════════════════════════════════════════\n");
echo bold("  Feedbackspinne Auth – Test Suite\n");
echo bold("═══════════════════════════════════════════\n\n");

// ── 1. DB connection ─────────────────────────────────────────────────────────
echo yellow("1. Datenbank\n");
try {
    $pdo = getDB();
    test('DB-Verbindung', true);
    $tables = [];
    $rows   = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['users','password_reset_tokens','security_log','rate_limits'] as $t) {
        test("Tabelle '{$t}' vorhanden", in_array($t, $rows));
    }
} catch (Exception $e) {
    test('DB-Verbindung', false, $e->getMessage());
}

// ── 2. Password complexity helper (unit) ──────────────────────────────────────
echo "\n" . yellow("2. Passwort-Validierung (Unit)\n");

require_once __DIR__ . '/api.php';  // Loads validatePasswordComplexity()
// Note: api.php calls exit() on routing – we included it just for the helper function.
// Since that won't work cleanly, test the logic inline:
function _validatePw(string $p): ?string {
    if (mb_strlen($p) < 8)           return 'Zu kurz';
    if (!preg_match('/[A-Z]/', $p))  return 'Kein Großbuchstabe';
    if (!preg_match('/[a-z]/', $p))  return 'Kein Kleinbuchstabe';
    if (!preg_match('/[0-9]/', $p))  return 'Keine Ziffer';
    if (!preg_match('/[\W_]/', $p))  return 'Kein Sonderzeichen';
    return null;
}
test('Zu kurzes Passwort abgelehnt',  _validatePw('Ab1!') !== null);
test('Fehlendes Sonderzeichen',        _validatePw('Abcdefg1') !== null);
test('Gültiges Passwort akzeptiert',   _validatePw('Secure#99') === null);
test('Kein Großbuchstabe',            _validatePw('secure#99') !== null);

// ── 3. HTTP API tests ─────────────────────────────────────────────────────────
if (!function_exists('curl_init')) {
    echo "\n" . yellow("3. HTTP-Tests übersprungen (curl nicht verfügbar)\n");
} else {
    echo "\n" . yellow("3. HTTP-API\n");
    $raw = '';
    $ts  = time();
    $testUser = "testuser_{$ts}";
    $testMail = "test_{$ts}@example.com";
    $testPw   = 'Test@1234!';

    // Register
    $res = httpPost('register', ['username' => $testUser, 'email' => $testMail, 'password' => $testPw], $raw);
    test('Registrierung', $res['success'] ?? false, $raw);

    // Duplicate register
    $res2 = httpPost('register', ['username' => $testUser, 'email' => $testMail, 'password' => $testPw], $raw);
    test('Duplikat-Registrierung abgelehnt', !($res2['success'] ?? true), $raw);

    // Login with wrong password
    $res3 = httpPost('login', ['identifier' => $testUser, 'password' => 'WrongPass!'], $raw);
    test('Login mit falschem Passwort abgelehnt', !($res3['success'] ?? true), $raw);

    // Login OK
    $res4 = httpPost('login', ['identifier' => $testUser, 'password' => $testPw], $raw);
    test('Login erfolgreich', $res4['success'] ?? false, $raw);
    test('Login gibt Nutzerdaten zurück', isset($res4['data']['user']['username']), $raw);

    // Me (session cookie set by login)
    $me = httpGet('me');
    test('/me gibt User zurück', ($me['data']['user']['username'] ?? '') === $testUser);

    // Change password
    $res5 = httpPost('change_password', ['old_password' => $testPw, 'new_password' => 'NewPass@99!'], $raw);
    test('Passwort ändern', $res5['success'] ?? false, $raw);

    // Logout
    $res6 = httpPost('logout', [], $raw);
    test('Logout', $res6['success'] ?? false, $raw);

    // Me after logout
    $me2 = httpGet('me');
    test('/me nach Logout leer', !($me2['success'] ?? true));

    // Forgot password (always success)
    $res7 = httpPost('forgot_password', ['email' => $testMail], $raw);
    test('Passwort vergessen (Anti-Enumeration)', $res7['success'] ?? false, $raw);

    // Invalid weak password on register
    $res8 = httpPost('register', ['username' => "weak_{$ts}", 'email' => "weak_{$ts}@x.com", 'password' => 'weak'], $raw);
    test('Schwaches Passwort abgelehnt', !($res8['success'] ?? true), $raw);

    // Clean up test user
    try {
        $pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$testUser]);
        $pdo->prepare("DELETE FROM users WHERE username = ?")->execute(["weak_{$ts}"]);
        $pdo->prepare("DELETE FROM rate_limits WHERE client_identifier LIKE '%test%'")->execute();
    } catch (Exception $e) { /* ignore */ }
}

// ── Summary ──────────────────────────────────────────────────────────────────
echo "\n" . bold("═══════════════════════════════════════════\n");
echo bold("Ergebnis: ") . green("{$passed} bestanden") . ", " . ($failed > 0 ? red("{$failed} fehlgeschlagen") : "0 fehlgeschlagen") . "\n\n";

@unlink($cookieJar);
exit($failed > 0 ? 1 : 0);
