# Sicherheitsaudit: Feedbackspinne

**Datum:** 2026-01-18
**Projekt:** Feedbackspinne - Kollaboratives Reflexionstool
**Technologie:** PHP 8+, MySQL, Vanilla JavaScript

---

## Zusammenfassung

Das Projekt zeigt **gute grundlegende Sicherheitspraktiken** (Prepared Statements, Password Hashing, XSS-Schutz), weist jedoch **kritische Schwachstellen** in der Konfigurationsverwaltung und Authentifizierung auf, die sofort behoben werden müssen.

### Sicherheitsbewertung: ⚠️ MITTEL-KRITISCH

**Stärken:**
- ✅ Prepared Statements für alle Datenbankabfragen
- ✅ Sichere Passwort-Hashing mit `password_verify()`
- ✅ Konsequente Ausgabebereinigung mit `htmlspecialchars()`
- ✅ Session-basierte Zugriffskontrolle mit Ownership-Checks
- ✅ CSRF-Token-Infrastruktur vorhanden

**Kritische Schwachstellen:**
- 🔴 Hardcodierte Datenbank-Credentials in Versionskontrolle
- 🔴 Standard-Admin-Passwort im UI angezeigt
- 🔴 Fehlende CSRF-Validierung beim Login
- 🔴 Keine Rate-Limiting-Mechanismen

---

## 🔴 DRINGEND (Sofortige Maßnahmen erforderlich)

### 1. **Exponierte Datenbank-Credentials** ⚠️ KRITISCH

**Datei:** `config.php` (Zeilen 8-11)

**Problem:**
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'd045e8fc');
define('DB_USER', 'd045e8fc');
define('DB_PASS', 'p9TPcEDsnMsvLaUVMGqp'); // HARDCODIERT!
```

**Risiko:**
- Wenn das Repository öffentlich ist oder geleakt wird, hat ein Angreifer vollständigen Datenbankzugriff
- Komplette Kompromittierung aller Daten (Admin-Accounts, Session-Daten, Teilnehmer-Feedback)
- Mögliche Datenmanipulation oder -löschung

**Lösung:**
1. Credentials in `.env`-Datei auslagern (NICHT versioniert)
2. `.env` zur `.gitignore` hinzufügen
3. `.env.example` mit Platzhaltern erstellen
4. PHP-Library wie `vlucas/phpdotenv` verwenden oder eigene Loader-Funktion

**Implementierung:**
```php
// .env (NICHT COMMITTEN!)
DB_HOST=localhost
DB_NAME=d045e8fc
DB_USER=d045e8fc
DB_PASS=p9TPcEDsnMsvLaUVMGqp

// config.php
define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST'));
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME'));
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER'));
define('DB_PASS', $_ENV['DB_PASS'] ?? getenv('DB_PASS'));
```

---

### 2. **Standard-Admin-Credentials im UI** ⚠️ KRITISCH

**Datei:** `admin/index.php` (Zeilen 169-175)

**Problem:**
```html
<div class="info">
    <strong>Standard-Login:</strong><br>
    Benutzername: admin<br>
    Passwort: admin123<br>
    <em>⚠️ Bitte ändere das Passwort nach dem ersten Login!</em>
</div>
```

**Risiko:**
- Jeder kann sich als Admin einloggen, wenn das Passwort nicht geändert wurde
- Vollständiger Zugriff auf alle Sessions, Teilnehmer-Daten und Administratorfunktionen
- Öffentliche Dokumentation der Standard-Credentials

**Lösung:**
1. Standard-Credentials **komplett entfernen** aus dem UI
2. Passwortänderungs-Erzwingung beim ersten Login implementieren
3. Hinweis nur in separater Dokumentation (NICHT im Code/UI)

---

### 3. **Fehlende CSRF-Validierung beim Login** ⚠️ HOCH

**Datei:** `admin/index.php` (Zeile 11, 154)

**Problem:**
- CSRF-Token wird generiert (Zeile 154), aber **nicht validiert** (Zeile 11)
- Login-Request prüft nur Benutzername/Passwort

**Risiko:**
- Login-CSRF-Angriffe möglich
- Angreifer kann Opfer in fremden Account einloggen (Session Fixation)

**Lösung:**
```php
// admin/index.php, Zeile 11 (nach POST-Check)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Validierung HINZUFÜGEN:
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ungültige Anfrage. Bitte versuche es erneut.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        // ... Rest des Codes
    }
}
```

---

### 4. **Kein Rate Limiting** ⚠️ HOCH

**Betroffene Dateien:**
- `admin/index.php` (Admin-Login)
- `index.php` (Session-Code-Eingabe)

**Problem:**
- Keine Beschränkung fehlgeschlagener Login-Versuche
- 4-stellige Session-Codes (nur 10.000 Möglichkeiten) können durchprobiert werden
- Kein Account-Lockout bei Brute-Force-Angriffen

**Risiko:**
- Brute-Force-Angriffe auf Admin-Passwörter
- Automatisiertes Erraten von Session-Codes
- DoS durch massenhafte Anfragen

**Lösung:**
Implementiere Rate Limiting mit:
1. IP-basierte Anfragenbegrenzung (z.B. max. 5 Versuche in 15 Minuten)
2. Account-Lockout nach 5 fehlgeschlagenen Logins
3. Progressive Delays (exponential backoff)
4. CAPTCHA nach mehreren Fehlversuchen

**Beispiel-Implementierung:**
```php
// Einfaches IP-basiertes Rate Limiting
function checkRateLimit($key, $maxAttempts = 5, $timeWindow = 900) {
    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 0, 'first_attempt' => time()];
    }

    $data = &$_SESSION['rate_limit'][$key];

    if (time() - $data['first_attempt'] > $timeWindow) {
        $data = ['count' => 1, 'first_attempt' => time()];
        return true;
    }

    if ($data['count'] >= $maxAttempts) {
        return false;
    }

    $data['count']++;
    return true;
}
```

---

### 5. **Unvollständige Logout-Implementierung** ⚠️ MITTEL

**Datei:** `admin/logout.php` (Zeile 5)

**Problem:**
```php
session_destroy(); // Nur Session zerstören
```

**Risiko:**
- Session-Variablen bleiben möglicherweise bestehen
- Session-Fixation-Risiko
- Unvollständige Bereinigung

**Lösung:**
```php
// admin/logout.php - VOLLSTÄNDIGER Logout
session_start();
$_SESSION = array(); // Alle Session-Variablen löschen

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

session_destroy();
header('Location: ../index.php');
exit;
```

---

### 6. **Hardcodierter SECURE_KEY nicht verwendet** ⚠️ MITTEL

**Datei:** `config.php` (Zeile 19)

**Problem:**
```php
define('SECURE_KEY', '6g4uJ$bCA^o)nZb;!>6-H=yYbFA(QH[-'); // Hardcodiert, aber ungenutzt
```

**Risiko:**
- Wenn dieser Key später für Verschlüsselung/Signierung verwendet wird, ist er bereits kompromittiert
- Hardcodierte Keys sollten generell vermieden werden

**Lösung:**
1. Falls nicht benötigt: **Entfernen**
2. Falls für zukünftige Verschlüsselung geplant: In `.env` auslagern
3. Bei Verwendung: Regelmäßige Rotation implementieren

---

## 🟡 MITTELFRISTIG (Innerhalb 1-2 Wochen beheben)

### 7. **Session-Timeout nicht durchgesetzt**

**Datei:** `config.php` (Zeile 16)

**Problem:**
```php
define('SESSION_TIMEOUT', 3600); // Definiert, aber nie geprüft
```

**Risiko:**
- Admin-Sessions laufen nie ab
- Erhöhtes Risiko bei Session-Hijacking
- Unbegrenzte Gültigkeit öffentlicher Computer

**Lösung:**
```php
// In requireAdmin() oder zu Beginn jeder Admin-Seite:
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_destroy();
            header('Location: /admin/index.php?timeout=1');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}
```

---

### 8. **Keine HTTPS-Erzwingung**

**Problem:**
- Keine Prüfung oder Weiterleitung zu HTTPS
- Sensitive Daten (Passwörter, Session-Cookies) können über HTTP übertragen werden

**Risiko:**
- Man-in-the-Middle-Angriffe
- Passwörter im Klartext abfangbar
- Session-Hijacking über unsichere Verbindungen

**Lösung:**
```php
// config.php - HTTPS erzwingen (Produktion)
if (!DEBUG_MODE && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// Session-Cookie-Sicherheit
session_set_cookie_params([
    'lifetime' => SESSION_TIMEOUT,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,      // Nur über HTTPS
    'httponly' => true,    // Kein JavaScript-Zugriff
    'samesite' => 'Strict' // CSRF-Schutz
]);
```

---

### 9. **Keine Eingabelängen-Validierung**

**Betroffene Dateien:**
- `session.php` (Teilnehmername)
- `admin/create.php` (Session-Titel, Beschreibung, Dimensionen)

**Problem:**
- Keine maximale Länge für Texteingaben definiert
- Mögliche DoS durch extrem lange Inputs

**Risiko:**
- Datenbank-Überlastung
- UI-Rendering-Probleme
- Potenzielle Buffer-Overflow-ähnliche Szenarien

**Lösung:**
```php
// Beispiel für session.php
$participantName = trim($_POST['participant_name'] ?? '');
if (strlen($participantName) > 100) {
    $error = 'Name zu lang (max. 100 Zeichen).';
}

// Für admin/create.php
$title = trim($_POST['title'] ?? '');
if (strlen($title) > 200) {
    $error = 'Titel zu lang (max. 200 Zeichen).';
}
```

---

### 10. **Fehlende Content Security Policy (CSP)**

**Problem:**
- Keine CSP-Header gesetzt
- Kein Schutz gegen XSS-Angriffe durch externe Scripts

**Risiko:**
- Cross-Site-Scripting (XSS) trotz `htmlspecialchars()`
- Einbindung bösartiger externer Ressourcen

**Lösung:**
```php
// config.php - CSP-Header hinzufügen
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
```

---

### 11. **SQL-Injection-Risiko bei direkter JSON-Dekodierung**

**Datei:** `admin/results.php`, `session.php`

**Problem:**
- JSON-dekodierte Daten werden direkt verwendet
- Theoretisches Risiko bei manipulierten JSON-Daten in der Datenbank

**Aktueller Status:** Niedrig-Risiko (durch Prepared Statements geschützt)

**Verbesserung:**
```php
// Zusätzliche Validierung nach JSON-Dekodierung
$dimensions = json_decode($session['dimensions'], true);
if (!is_array($dimensions)) {
    die('Ungültige Session-Daten');
}
```

---

### 12. **Fehlende Logging-Mechanismen**

**Problem:**
- Keine Protokollierung von:
  - Fehlgeschlagenen Login-Versuchen
  - Session-Erstellungen/-Löschungen
  - Datenbankfehlern
  - Sicherheitsrelevanten Ereignissen

**Risiko:**
- Keine Nachvollziehbarkeit bei Sicherheitsvorfällen
- Keine Erkennung von Angriffsmustern
- Compliance-Probleme (DSGVO-Anforderungen)

**Lösung:**
```php
// Einfaches Logging-System
function logSecurityEvent($type, $message, $context = []) {
    $logFile = __DIR__ . '/logs/security.log';
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $type,
        'message' => $message,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'context' => $context
    ];
    file_put_contents($logFile, json_encode($entry) . PHP_EOL, FILE_APPEND);
}

// Verwendung:
logSecurityEvent('LOGIN_FAILED', 'Failed login attempt', ['username' => $username]);
```

---

## 🟢 WÜNSCHENSWERT (Langfristige Verbesserungen)

### 13. **Zwei-Faktor-Authentifizierung (2FA)**

**Beschreibung:**
Implementierung von TOTP-basierter 2FA für Admin-Accounts

**Vorteile:**
- Schutz auch bei kompromittierten Passwörtern
- Moderne Sicherheitsstandards
- Vertrauen der Nutzer

**Libraries:**
- `sonata-project/google-authenticator`
- `robthree/twofactorauth`

---

### 14. **Passwort-Komplexitätsanforderungen**

**Aktuell:**
- Keine Mindestanforderungen an Passwörter
- Standard-Passwort "admin123" ist sehr schwach

**Empfehlungen:**
- Mindestens 12 Zeichen
- Mix aus Groß-/Kleinbuchstaben, Zahlen, Sonderzeichen
- Überprüfung gegen häufige Passwörter (Have I Been Pwned API)
- Passwortänderung beim ersten Login erzwingen

---

### 15. **Datenbankschema-Verbesserungen**

**Empfehlungen:**

1. **Soft Deletes:** Statt Sessions zu löschen, `deleted_at` Timestamp setzen
2. **Audit Trail:** Tabelle für alle Änderungen (created_at, updated_at, updated_by)
3. **IP-Logging:** IP-Adresse bei Submissions speichern (DSGVO beachten!)
4. **Session-Ablaufdatum:** `expires_at` Feld für zeitbegrenzte Sessions

```sql
-- Beispiel: Audit-Tabelle
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    action ENUM('CREATE', 'UPDATE', 'DELETE') NOT NULL,
    user_id INT,
    changes JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id)
);
```

---

### 16. **API-Versionierung und RESTful Struktur**

**Aktuell:**
- Mischung aus HTML-Rendering und Datenverarbeitung
- `jsonResponse()` Funktion vorhanden, aber wenig genutzt

**Verbesserung:**
- Trennung von API-Endpunkten und Views
- Struktur: `/api/v1/sessions`, `/api/v1/submissions`
- Ermöglicht zukünftige Mobile Apps oder SPA-Frontend

---

### 17. **Automatisierte Sicherheitstests**

**Tools:**
1. **OWASP ZAP:** Automatisierte Penetrationstests
2. **PHPStan/Psalm:** Statische Code-Analyse
3. **Snyk:** Dependency-Scanning
4. **SonarQube:** Code-Qualität und Sicherheit

**CI/CD Integration:**
```yaml
# .github/workflows/security.yml
name: Security Scan
on: [push, pull_request]
jobs:
  security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run PHPStan
        run: composer require --dev phpstan/phpstan && vendor/bin/phpstan analyse src
```

---

### 18. **Input Sanitization Library**

**Aktuell:**
- Manuelle `htmlspecialchars()` Aufrufe
- Fehleranfällig bei Vergessen

**Verbesserung:**
Verwende Libraries wie:
- `ezyang/htmlpurifier` für HTML-Bereinigung
- `symfony/validator` für komplexe Validierungen

---

### 19. **Datenschutz (DSGVO-Konformität)**

**Zu prüfen:**

1. **Datenschutzerklärung:** Welche Daten werden gespeichert?
2. **Einwilligung:** Informierte Zustimmung der Teilnehmer
3. **Auskunftsrecht:** Können Nutzer ihre Daten abrufen?
4. **Löschrecht:** Können Teilnehmer Löschung verlangen?
5. **Datenminimierung:** Werden nur notwendige Daten gespeichert?
6. **Auftragsverarbeitung:** Vertrag mit Hosting-Provider

**Empfehlungen:**
- Cookie-Banner (falls Cookies verwendet werden)
- Anonymisierung von Teilnehmer-Namen optional machen
- Automatische Löschung alter Sessions (nach z.B. 90 Tagen)

---

### 20. **Backup- und Recovery-Strategie**

**Aktuell:**
- Keine erkennbare Backup-Strategie

**Empfehlungen:**
1. Automatisierte tägliche Datenbank-Backups
2. Backup-Rotation (7 Tage, 4 Wochen, 12 Monate)
3. Verschlüsselte Backup-Speicherung
4. Regelmäßige Recovery-Tests

```bash
# Beispiel: Cron-Job für MySQL-Backup
0 2 * * * mysqldump -u user -p'password' d045e8fc | gzip > /backups/feedbackspinne-$(date +\%Y\%m\%d).sql.gz
```

---

### 21. **Error Handling Verbesserungen**

**Aktuell:**
- `DEBUG_MODE` schaltet Fehler an/aus
- Generische Fehlermeldungen in Produktion

**Verbesserungen:**
```php
// Custom Error Handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logSecurityEvent('PHP_ERROR', $errstr, [
        'file' => $errfile,
        'line' => $errline,
        'errno' => $errno
    ]);

    if (DEBUG_MODE) {
        echo "Error: $errstr in $errfile:$errline";
    } else {
        echo "Ein Fehler ist aufgetreten. Bitte versuche es später erneut.";
    }
});
```

---

### 22. **Performance-Optimierungen**

**Vorschläge:**

1. **Datenbank-Indizes:**
```sql
CREATE INDEX idx_sessions_code ON sessions(code);
CREATE INDEX idx_sessions_active ON sessions(is_active);
CREATE INDEX idx_submissions_session ON submissions(session_id);
```

2. **Query-Caching:** Für häufig abgerufene Sessions

3. **Lazy Loading:** Chart.js nur laden wenn benötigt

4. **Asset Minification:** CSS/JS komprimieren

---

## Priorisierte Umsetzungsreihenfolge

### Sofort (Diese Woche):
1. ✅ Datenbank-Credentials in `.env` auslagern
2. ✅ Standard-Admin-Credentials aus UI entfernen
3. ✅ CSRF-Validierung beim Login implementieren
4. ✅ Logout-Funktion vervollständigen

### Woche 2:
5. ✅ Rate Limiting implementieren
6. ✅ Session-Timeout durchsetzen
7. ✅ HTTPS-Erzwingung aktivieren
8. ✅ Security Headers (CSP) hinzufügen

### Woche 3-4:
9. ✅ Logging-System implementieren
10. ✅ Eingabelängen-Validierung
11. ✅ Passwort-Komplexitätsanforderungen
12. ✅ Datenbankschema-Verbesserungen

### Langfristig (1-3 Monate):
13. ✅ 2FA implementieren
14. ✅ DSGVO-Konformität sicherstellen
15. ✅ Automatisierte Tests einrichten
16. ✅ Backup-Strategie implementieren

---

## Testplan

### Sicherheitstests durchführen:

1. **Authentifizierung:**
   - [ ] Brute-Force-Angriff auf Login simulieren
   - [ ] Session-Hijacking versuchen
   - [ ] CSRF-Angriff auf Login testen

2. **Autorisierung:**
   - [ ] Zugriff auf fremde Sessions ohne Login
   - [ ] Zugriff auf fremde Admin-Bereiche

3. **Input-Validierung:**
   - [ ] SQL-Injection-Versuche
   - [ ] XSS-Payloads in Formularen
   - [ ] Überlange Eingaben testen

4. **Session-Management:**
   - [ ] Session-Fixation testen
   - [ ] Timeout-Mechanismus prüfen
   - [ ] Logout-Vollständigkeit verifizieren

---

## Zusammenfassung der Risikobewertung

| Kategorie | Anzahl | Kritikalität |
|-----------|--------|--------------|
| 🔴 DRINGEND | 6 | HOCH-KRITISCH |
| 🟡 MITTELFRISTIG | 6 | MITTEL |
| 🟢 WÜNSCHENSWERT | 10 | NIEDRIG |

**Gesamtrisiko vor Fixes:** ⚠️ **HOCH**
**Geschätztes Risiko nach Dringend-Fixes:** 🟡 **MITTEL**
**Geschätztes Risiko nach allen Fixes:** ✅ **NIEDRIG**

---

## Kontakt & Unterstützung

Bei Fragen zur Umsetzung oder weiteren Sicherheitsbedenken:
- Sicherheitsrichtlinien: OWASP Top 10 (https://owasp.org/www-project-top-ten/)
- PHP Security Best Practices: https://www.php.net/manual/de/security.php

**Nächste Schritte:** Beginne mit den 🔴 DRINGEND-Maßnahmen und arbeite die Liste systematisch ab.
