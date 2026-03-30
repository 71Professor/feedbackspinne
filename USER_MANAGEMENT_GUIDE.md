# User-Management Implementierungsanleitung

Dieses Dokument beschreibt, wie das User-Management (Registrierung, E-Mail-Verifikation, Login, Passwort vergessen/zurücksetzen) in **feedbackspinne** implementiert ist. Es dient als Blaupause zur Übernahme in andere Projekte.

---

## Tech Stack

| Komponente | Detail |
|---|---|
| Sprache | PHP 8.0+ (kein Framework, Vanilla PHP) |
| Datenbank | MySQL 5.7+ / MariaDB 10.2+ |
| DB-Zugriff | PDO mit Prepared Statements (kein ORM) |
| E-Mail | PHPMailer (via Composer) |
| Passwort-Hashing | `password_hash()` mit `PASSWORD_BCRYPT`, cost=12 |
| Auth-Methode | PHP Sessions (Cookie-basiert) |

---

## Datenbankschema

### `users`

```sql
CREATE TABLE users (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username          VARCHAR(50) NOT NULL,
    email             VARCHAR(255) NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    is_active         TINYINT(1) NOT NULL DEFAULT 1,
    is_email_verified TINYINT(1) NOT NULL DEFAULT 0,
    email_verified_at DATETIME NULL DEFAULT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email (email),
    INDEX idx_email_verified (is_email_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Validierungsregeln:**
- `username`: 3–50 Zeichen, Regex `/^[a-zA-Z0-9_\-]{3,50}$/`
- `email`: `filter_var($email, FILTER_VALIDATE_EMAIL)`, max. 255 Zeichen
- `password`: mind. 8 Zeichen, je 1× Groß-, Kleinbuchstabe, Ziffer, Sonderzeichen

### `email_verification_tokens`

```sql
CREATE TABLE email_verification_tokens (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    token      VARCHAR(64) NOT NULL,   -- Raw-Token (bin2hex(random_bytes(32)))
    expires_at DATETIME NOT NULL,       -- +24 Stunden
    used       TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY fk_evt_user (user_id),
    CONSTRAINT fk_evt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> **Achtung:** Der Token wird hier als **Klartext** (nicht gehasht) in der DB gespeichert,
> weil er nur kurzlebig und nicht sicherheitskritisch wie ein Passwort ist.
> Beim Password-Reset (unten) wird anders vorgegangen.

### `password_reset_tokens`

```sql
CREATE TABLE password_reset_tokens (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    token_hash VARCHAR(64) NOT NULL,   -- SHA256-Hash des Raw-Tokens
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,       -- +1 Stunde
    used_at    DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token (token_hash),
    KEY fk_prt_user (user_id),
    CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> **Wichtig:** Der Reset-Token wird als **SHA256-Hash** gespeichert (`hash('sha256', $rawToken)`).
> Nur der Raw-Token wird per E-Mail verschickt. So kann ein DB-Leak nicht direkt
> zum Account-Zugriff missbraucht werden.

### `rate_limits`

```sql
CREATE TABLE rate_limits (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    limit_key          VARCHAR(50) NOT NULL,
    client_identifier  VARCHAR(200) NOT NULL,
    attempt_count      INT NOT NULL DEFAULT 0,
    first_attempt_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_attempt_at    DATETIME NULL DEFAULT NULL,
    blocked_until      DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_key_client (limit_key, client_identifier),
    KEY idx_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### `security_log`

```sql
CREATE TABLE security_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(50) NOT NULL,
    user_id    INT UNSIGNED NULL DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    details    TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_event (event_type),
    KEY idx_user (user_id),
    KEY idx_ip (ip_address),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Dateistruktur

```
auth/
├── api.php        # Alle REST-Endpoints (register, login, forgot_password, ...)
├── mailer.php     # PHPMailer-Wrapper (sendVerificationEmail, sendPasswordResetEmail)
├── logger.php     # logSecurityEvent() Funktion
├── setup.php      # DB-Migration: erstellt alle Tabellen
├── app.js         # Frontend-JS für alle Auth-Formulare
└── index.html     # HTML-Templates für alle Auth-Views (Register, Login, Reset, ...)
config.php         # DB-Verbindung, Session-Config, CSRF, Rate Limiting
.env               # Umgebungsvariablen (nicht im Git!)
.env.example       # Vorlage für .env
```

---

## Umgebungsvariablen (`.env`)

```env
# Datenbank
DB_HOST=localhost
DB_NAME=deine_datenbank
DB_USER=dein_benutzer
DB_PASS=dein_passwort

# App
APP_URL=https://deine-domain.de
SECURE_KEY=ein_langer_zufaelliger_string
DEBUG_MODE=false

# SMTP (für PHPMailer)
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=dein-smtp-user@example.com
SMTP_PASS=dein-smtp-passwort
SMTP_FROM_EMAIL=noreply@deine-domain.de
SMTP_FROM_NAME=Projektname
```

---

## Session-Konfiguration

In `config.php` werden folgende INI-Einstellungen **vor** `session_start()` gesetzt:

```php
ini_set('session.cookie_httponly', '1');      // JS kann Cookie nicht lesen (XSS-Schutz)
ini_set('session.cookie_secure', '1');         // Nur über HTTPS (auf Prod)
ini_set('session.cookie_samesite', 'Lax');     // CSRF-Schutz
ini_set('session.use_strict_mode', '1');       // Nur server-generierte Session-IDs

define('USER_SESSION_NAME', 'myapp_user');     // Session-Key anpassen!
define('USER_SESSION_TIMEOUT', 1800);          // 30 Minuten Inaktivitäts-Timeout
```

**Session-Variablen nach Login:**

```php
$_SESSION[USER_SESSION_NAME] = true;
$_SESSION['user_id']         = $user['id'];
$_SESSION['user_username']   = $user['username'];
$_SESSION['user_email']      = $user['email'];
$_SESSION['last_activity']   = time();
```

**Middleware-Funktionen:**

```php
function isUserLoggedIn(): bool {
    return isset($_SESSION[USER_SESSION_NAME]) && $_SESSION[USER_SESSION_NAME] === true;
}

function checkSessionTimeout(): void {
    if (!isset($_SESSION['last_activity'])) { return; }
    if (time() - $_SESSION['last_activity'] > USER_SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return;
    }
    $_SESSION['last_activity'] = time();
}

function requireUserSession(): void {
    checkSessionTimeout();
    if (!isUserLoggedIn()) {
        // JSON-Error oder Redirect je nach Kontext
        http_response_code(401);
        echo json_encode(['error' => 'Nicht eingeloggt.']);
        exit;
    }
}
```

---

## Rate Limiting

Alle Auth-Endpoints sind durch Rate Limiting geschützt. Die Implementierung ist DB-basiert.

**Grenzwerte:**

| Endpoint / Key        | Max. Versuche | Zeitfenster |
|-----------------------|---------------|-------------|
| `auth_register`       | 5             | 5 Minuten   |
| `auth_login`          | 5             | 5 Minuten   |
| `auth_forgot`         | 3             | 15 Minuten  |
| `auth_reset`          | 10            | 5 Minuten   |
| `email_verification`  | 10            | 10 Minuten  |
| `resend_verification` | 3             | 10 Minuten  |

**IP-Erkennung (Cloudflare-kompatibel):**

```php
function getClientIP(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
```

**Kernfunktionen:**

```php
function checkRateLimit(string $key, int $maxAttempts, int $timeWindow): bool { ... }
function incrementRateLimit(string $key): void { ... }
function resetRateLimit(string $key): void { ... }    // Nach Erfolg aufrufen!
function getRateLimitTimeRemaining(string $key): int { ... }
```

---

## CSRF-Schutz

```php
function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
```

- Token wird im HTML-Formular als `<input type="hidden" name="csrf_token">` eingebunden
- Bei jedem POST vor der Verarbeitung prüfen
- `hash_equals()` verwenden (timing-safe)

---

## Flow 1: Registrierung

**Endpoint:** `POST /auth/api.php?action=register`

**Request Body:**
```json
{ "username": "...", "email": "...", "password": "..." }
```

**Ablauf:**

```
1. checkRateLimit('auth_register', 5, 300)
   → 429 + Wartezeit wenn überschritten

2. Validierung:
   - Username: /^[a-zA-Z0-9_\-]{3,50}$/
   - Email: filter_var(..., FILTER_VALIDATE_EMAIL)
   - Passwort: validatePasswordComplexity() → 8 Zeichen, Groß/Klein/Zahl/Sonderzeichen
   → 422 bei Fehler

3. Duplikat-Check (timing-safe):
   SELECT id FROM users WHERE username = ? OR email = ?
   - Immer GLEICHE Fehlermeldung, egal ob Username oder E-Mail belegt
   - Verhindert User-Enumeration
   → 409 bei Duplikat

4. Password-Hash:
   $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

5. User anlegen:
   INSERT INTO users (username, email, password_hash, is_email_verified)
   VALUES (?, ?, ?, 0)

6. Verifikationstoken generieren:
   $token = bin2hex(random_bytes(32));  // 64 hex chars
   INSERT INTO email_verification_tokens (user_id, token, expires_at)
   VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))

7. Verifikations-E-Mail senden:
   sendVerificationEmail($email, $username, $token)
   - Fehler werden geloggt, blockieren aber NICHT die Registrierung

8. logSecurityEvent('register_success', $userId)

9. Response 200:
   { "message": "Registrierung erfolgreich. Bitte prüfe deine E-Mails.", "email": "..." }
```

**Passwort-Komplexität:**

```php
function validatePasswordComplexity(string $password): bool {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[a-z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    if (!preg_match('/[\W_]/', $password)) return false;
    return true;
}
```

---

## Flow 2: E-Mail-Verifikation

**Endpoint:** `POST /auth/api.php?action=verifyEmail`

**Request Body:** `{ "token": "..." }` (Token kommt aus URL-Parameter, wird per JS übergeben)

**Ablauf:**

```
1. checkRateLimit('email_verification', 10, 600)

2. Token-Lookup:
   SELECT evt.*, u.is_email_verified
   FROM email_verification_tokens evt
   JOIN users u ON u.id = evt.user_id
   WHERE evt.token = ?

3. Validierung:
   - Token nicht gefunden → 400
   - Bereits verifiziert (is_email_verified = 1) → 200 (freundliche Meldung)
   - used = 1 → 400 "Token bereits verwendet"
   - expires_at < NOW() → 400 "Token abgelaufen"

4. Transaktion:
   UPDATE users SET is_email_verified = 1, email_verified_at = NOW() WHERE id = ?
   UPDATE email_verification_tokens SET used = 1 WHERE id = ?

5. logSecurityEvent('verify_email_success', $userId)

6. Response 200: { "message": "E-Mail erfolgreich verifiziert." }
```

**Resend-Endpoint:** `POST /auth/api.php?action=resendVerificationEmail`

```
1. checkRateLimit('resend_verification', 3, 600)
2. E-Mail validieren (ungültige E-Mail → generische Erfolgsantwort, Anti-Enumeration)
3. User suchen (nicht gefunden → generische Erfolgsantwort)
4. Bereits verifiziert → 409 Conflict
5. Alle alten Tokens löschen: DELETE FROM email_verification_tokens WHERE user_id = ?
6. Neuen Token erzeugen und E-Mail senden
7. Immer generische Erfolgsantwort zurückgeben
```

---

## Flow 3: Login

**Endpoint:** `POST /auth/api.php?action=login`

**Request Body:** `{ "identifier": "username oder email", "password": "..." }`

**Ablauf:**

```
1. checkRateLimit('auth_login', 5, 300)

2. User-Lookup:
   SELECT * FROM users
   WHERE (username = ? OR email = ?) AND is_active = 1
   LIMIT 1

3. Timing-safe Passwort-Prüfung:
   if ($user === null) {
       // Dummy-Hash um Timing-Angriffe zu verhindern
       password_verify($password, '$2y$12$invaliddummyhashfortimingatk.xxxxxxxxxx');
       → 401 "Ungültige Anmeldedaten"
   }
   if (!password_verify($password, $user['password_hash'])) {
       incrementRateLimit('auth_login');
       → 401 "Ungültige Anmeldedaten"
   }
   // NIEMALS unterschiedliche Meldungen für "User nicht gefunden" vs. "Passwort falsch"!

4. E-Mail-Verifikation prüfen:
   if ($user['is_email_verified'] == 0) {
       → 403 { "error": "...", "email_unverified": true }
   }
   // Frontend zeigt dann Button zum erneuten Senden der Verifikationsmail

5. Rate Limit zurücksetzen (nach Erfolg):
   resetRateLimit('auth_login');

6. Session regenerieren (Session Fixation verhindern):
   session_regenerate_id(true);

7. Session-Variablen setzen:
   $_SESSION[USER_SESSION_NAME] = true;
   $_SESSION['user_id']         = $user['id'];
   $_SESSION['user_username']   = $user['username'];
   $_SESSION['user_email']      = $user['email'];
   $_SESSION['last_activity']   = time();

8. logSecurityEvent('login_success', $user['id'])

9. Response 200: { "user": { "id": ..., "username": "...", "email": "..." } }
```

---

## Flow 4: Passwort vergessen

**Endpoint:** `POST /auth/api.php?action=forgot_password`

**Request Body:** `{ "email": "..." }`

**Ablauf:**

```
1. checkRateLimit('auth_forgot', 3, 900)
   → Auch bei Überschreitung: IMMER generische Erfolgsantwort zurückgeben

2. E-Mail validieren (ungültige E-Mail → generische Erfolgsantwort, Registrierung fortsetzen NICHT)

3. User suchen:
   SELECT id, username FROM users WHERE email = ? AND is_active = 1

4. Wenn User gefunden:
   a. Alte ungenutzte Tokens löschen:
      DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL

   b. Raw-Token generieren:
      $rawToken = bin2hex(random_bytes(32));  // 64 hex chars

   c. Token als SHA256-Hash speichern:
      $tokenHash = hash('sha256', $rawToken);
      INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
      VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))

   d. Reset-E-Mail mit Raw-Token versenden:
      sendPasswordResetEmail($email, $username, $rawToken)
      URL in E-Mail: APP_URL + '/auth/?page=reset&token=' + $rawToken

5. IMMER dieselbe generische Antwort zurückgeben:
   { "message": "Falls diese E-Mail registriert ist, wurde eine Nachricht gesendet." }
   → Verhindert E-Mail-Enumeration!
```

---

## Flow 5: Passwort zurücksetzen

**Endpoint:** `POST /auth/api.php?action=reset_password`

**Request Body:** `{ "token": "...", "password": "..." }` (Token aus URL-Parameter via JS)

**Ablauf:**

```
1. checkRateLimit('auth_reset', 10, 300)

2. Validierung:
   - Token vorhanden?
   - Passwort-Komplexität: validatePasswordComplexity()
   → 422 bei Fehler

3. Token-Lookup:
   $tokenHash = hash('sha256', $rawToken);
   SELECT prt.*, u.username
   FROM password_reset_tokens prt
   JOIN users u ON u.id = prt.user_id
   WHERE prt.token_hash = ?
     AND prt.used_at IS NULL
     AND prt.expires_at > NOW()
   LIMIT 1
   → 400 "Ungültiger oder abgelaufener Token" wenn nicht gefunden

4. Transaktion (atomar!):
   $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
   UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?
   UPDATE users SET password_hash = ? WHERE id = ?

5. logSecurityEvent('reset_password_success', $userId)

6. Response 200: { "message": "Passwort erfolgreich zurückgesetzt." }
```

---

## E-Mail-Versand mit PHPMailer

**`auth/mailer.php`:**

```php
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

function createMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USER'];
    $mail->Password   = $_ENV['SMTP_PASS'];
    $port = (int)$_ENV['SMTP_PORT'];
    $mail->SMTPSecure = ($port === 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $port;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
    return $mail;
}

function sendMail(string $toEmail, string $toName, string $subject, string $html, string $text): bool {
    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = $text;
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('Mailer Error: ' . $e->getMessage());
        return false;
    }
}

function sendVerificationEmail(string $toEmail, string $toName, string $token): bool {
    $url  = $_ENV['APP_URL'] . '/auth/?page=verify&token=' . $token;
    $html = "...HTML mit Button und Link...";
    $text = "Verifikationslink (gültig 24 Stunden):\n" . $url;
    return sendMail($toEmail, $toName, 'E-Mail-Adresse bestätigen', $html, $text);
}

function sendPasswordResetEmail(string $toEmail, string $toName, string $token): bool {
    $url  = $_ENV['APP_URL'] . '/auth/?page=reset&token=' . $token;
    $html = "...HTML mit Button und Link...";
    $text = "Passwort-Reset-Link (gültig 60 Minuten):\n" . $url;
    return sendMail($toEmail, $toName, 'Passwort zurücksetzen', $html, $text);
}
```

> **Hinweis:** E-Mail-Fehler werden nur geloggt und blockieren niemals den Hauptablauf.
> Ein Registrierungsfehler wegen E-Mail-Problem wäre für den User frustrierend.

---

## Security Logging

**`auth/logger.php`:**

```php
function logSecurityEvent(string $eventType, ?int $userId = null, ?string $details = null): void {
    // INSERT INTO security_log (event_type, user_id, ip_address, user_agent, details)
    // VALUES (?, ?, ?, ?, ?)
}
```

**Zu loggende Events:**

| Event | Wann |
|---|---|
| `register_success` | Erfolgreiche Registrierung |
| `register_duplicate` | Username/E-Mail bereits vorhanden |
| `register_mail_failed` | Verifikationsmail konnte nicht gesendet werden |
| `login_success` | Erfolgreicher Login |
| `login_fail` | Falsches Passwort / User nicht gefunden |
| `login_unverified` | Login-Versuch mit unverifizierter E-Mail |
| `logout` | Logout |
| `forgot_password` | Reset-E-Mail angefordert |
| `reset_password_success` | Passwort erfolgreich zurückgesetzt |
| `reset_invalid_token` | Ungültiger/abgelaufener Reset-Token |
| `verify_email_success` | E-Mail erfolgreich verifiziert |
| `verify_email_expired_token` | Abgelaufener Verifikationstoken |
| `session_timeout` | Session abgelaufen |

---

## Sicherheits-Header

In `config.php` nach dem `session_start()` setzen:

```php
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
```

---

## Frontend (JavaScript)

**`auth/app.js`** – Muster für alle Formulare:

```javascript
// Formular abschicken
document.getElementById('form-register').addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = {
        username: document.getElementById('reg-username').value,
        email:    document.getElementById('reg-email').value,
        password: document.getElementById('reg-password').value,
    };
    try {
        const res = await fetch('/auth/api.php?action=register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (!res.ok) {
            showError(data.error);
            return;
        }
        showSuccess('Registrierung erfolgreich! Bitte prüfe deine E-Mails.');
        setTimeout(() => switchView('login'), 10000);
    } catch (err) {
        showError('Netzwerkfehler.');
    }
});

// Login mit Email-Unverified-Erkennung
document.getElementById('form-login').addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await fetch('/auth/api.php?action=login', { ... });
    const data = await res.json();
    if (res.status === 403 && data.email_unverified) {
        // Zeige Button "Verifikationsmail erneut senden"
        showResendSection(data.email);
        return;
    }
    if (!res.ok) { showError(data.error); return; }
    window.location.href = '/dashboard.php';
});

// Passwort-Reset: Token aus URL holen
const urlParams = new URLSearchParams(window.location.search);
const resetToken = urlParams.get('token');
document.getElementById('hidden-token').value = resetToken;
```

---

## Wichtigste Sicherheitsprinzipien (Zusammenfassung)

1. **Timing-safe Comparisons überall:** Niemals unterschiedliche Antwortzeiten je nach User-Existenz. Dummy-Hash beim Login, generische Antworten bei Forgot-Password und Resend.

2. **Token-Speicherung:**
   - E-Mail-Verifikation: Token im Klartext in DB (kurzlebig, kein Passwort-Äquivalent)
   - Password-Reset: Token als **SHA256-Hash** in DB, Klartext nur in der E-Mail

3. **Transaktionen für atomare Operationen:** Token-Invalidierung und Passwort-Update immer in einer Transaktion.

4. **Rate Limiting auf allen Endpoints:** Besonders auf Forgot-Password und Resend (niedrigere Limits).

5. **Session Regeneration:** `session_regenerate_id(true)` immer direkt nach erfolgreichem Login.

6. **Passwort-Reset invalidiert alte Tokens:** Vor dem Erstellen eines neuen Tokens alle alten `used_at IS NULL`-Tokens löschen.

7. **bcrypt cost=12:** Nicht unter 10, aber auch nicht so hoch, dass Login spürbar langsam wird.

8. **`hash_equals()`** für alle Token-Vergleiche (timing-safe).

9. **`filter_var(..., FILTER_VALIDATE_EMAIL)`** für E-Mail-Validierung – nie nur Regex.

10. **Fehler in E-Mail-Versand** nie nach oben propagieren. Nur loggen.
