# Implementierung: "Mein Konto" & "Abmelden" Buttons

Diese Dokumentation beschreibt die vollständige Implementierung der "Mein Konto"- und "Abmelden"-Buttons in der Feedbackspinne. Sie kann als Anleitung für ein anderes Projekt dienen.

---

## Inhaltsverzeichnis

1. [Überblick & Vorkommen](#1-überblick--vorkommen)
2. [HTML-Struktur](#2-html-struktur)
3. [CSS / Styling](#3-css--styling)
4. [JavaScript: Event-Handler & View-System](#4-javascript-event-handler--view-system)
5. [Backend PHP: Login & Logout](#5-backend-php-login--logout)
6. [Session-Management](#6-session-management)
7. [Sicherheitsfeatures](#7-sicherheitsfeatures)
8. [Komplette Flows](#8-komplette-flows)
9. [Dateistruktur](#9-dateistruktur)

---

## 1. Überblick & Vorkommen

Die Buttons erscheinen an **zwei verschiedenen Stellen**:

| Ort | Datei | Beschreibung |
|---|---|---|
| **Admin-Dashboard Header** | `admin/dashboard.php` (Z. 392–393) | Links (`<a>`), navigieren direkt per URL |
| **Konto-Dashboard View** | `auth/index.html` (Z. 222) | Button (`<button>`), löst API-Call aus |

---

## 2. HTML-Struktur

### 2.1 Admin-Dashboard Header (`admin/dashboard.php`)

Beide Buttons befinden sich im `.header-actions`-Container:

```html
<!-- Mein Konto -->
<a href="../auth/?view=account" class="btn btn-secondary">Mein Konto</a>

<!-- Abmelden -->
<a href="logout.php" class="btn btn-secondary">Abmelden</a>
```

- Einfache Anchor-Tags ohne JavaScript
- `Mein Konto` navigiert zur Auth-Seite mit `?view=account`
- `Abmelden` navigiert zu `logout.php` (serverseitiger Logout)

### 2.2 Auth / Konto-Dashboard (`auth/index.html`)

Die View `#view-dashboard` wird eingeblendet wenn der Nutzer eingeloggt ist:

```html
<div id="view-dashboard" class="card auth-view">
  <h2>Mein Konto</h2>
  <p class="subtitle">Eingeloggt als:</p>

  <!-- Nutzer-Info (wird per JS befüllt) -->
  <div class="user-bar">
    <div>
      <div class="name" id="dash-username"></div>
      <div class="email" id="dash-email"></div>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:12px;">
    <button class="btn btn-ghost" data-goto="changePassword">🔑 Passwort ändern</button>
    <button id="btn-logout" class="btn btn-primary">Abmelden</button>
    <button class="btn btn-ghost" style="color:#dc2626;border-color:#dc2626;" data-goto="deleteAccount">Konto löschen</button>
  </div>

  <div class="divider">oder</div>

  <a href="../admin/dashboard.php" class="btn btn-ghost">← Zur Feedbackspinne</a>
</div>
```

**Wichtig:**
- `id="btn-logout"` wird von JavaScript für den Event-Listener benötigt
- `data-goto="..."` Attribute werden vom View-Router verwendet (kein `id` nötig)
- Nutzerdaten (`#dash-username`, `#dash-email`) werden per JavaScript befüllt

---

## 3. CSS / Styling

Alle Styles befinden sich in `auth/styles.css`.

### 3.1 CSS-Variablen (Z. 3–20)

```css
:root {
  --green:    #7ab800;
  --green-2:  #5e9800;
  --green-bg: rgba(122,184,0,.08);
  --red:      #dc2626;
  --text:     #0f172a;
  --muted:    #64748b;
  --border:   #e5e7eb;
  --card:     #ffffff;
  --shadow:   0 10px 30px rgba(15,23,42,.07);
  --radius:   16px;
  --trans:    .18s ease;
}
```

### 3.2 Basis-Button (Z. 125–140)

```css
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 13px 20px;
  border: none;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background var(--trans), transform var(--trans), opacity var(--trans);
}
.btn:active   { transform: translateY(1px); }
.btn:disabled { opacity: .6; cursor: not-allowed; }
```

### 3.3 Button-Varianten (Z. 142–153)

```css
/* Gefüllt (primär) – wird für "Abmelden" im Konto-Dashboard verwendet */
.btn-primary {
  background: var(--green);   /* #7ab800 */
  color: #fff;
}
.btn-primary:hover:not(:disabled) { background: var(--green-2); }

/* Outline (ghost) – wird für Navigation verwendet */
.btn-ghost {
  background: transparent;
  color: var(--green-2);
  border: 1.5px solid var(--green);
}
.btn-ghost:hover:not(:disabled) { background: var(--green-bg); }
```

### 3.4 Nutzer-Bar (Z. 198–223)

```css
.user-bar {
  background: var(--green-bg);
  border: 1px solid rgba(122,184,0,.25);
  border-radius: 12px;
  padding: 14px 18px;
  margin-bottom: 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.user-bar .name  { font-weight: 600; font-size: 15px; }
.user-bar .email { font-size: 12px; color: var(--muted); }
```

**Hinweis:** Der Admin-Dashboard-Header hat ein eigenes Stylesheet – die `btn-secondary`-Klasse dort ist unabhängig vom Auth-Stylesheet definiert.

---

## 4. JavaScript: Event-Handler & View-System

Alle clientseitigen Logik ist in `auth/app.js` (Vanilla JS, keine Frameworks).

### 4.1 API-Wrapper (Z. 10–20)

```javascript
const API_BASE = 'api.php';

async function apiCall(action, body = null, method = 'POST') {
  const opts = {
    method,
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin', // Session-Cookie mitsenden!
  };
  if (body) opts.body = JSON.stringify(body);
  const url = `${API_BASE}?action=${action}`;
  const res  = await fetch(url, opts);
  return res.json();
}
```

**Schlüsselpunkt:** `credentials: 'same-origin'` sorgt dafür, dass das PHP-Session-Cookie bei jedem Request mitgeschickt wird.

### 4.2 View-Router (Z. 22–36)

```javascript
const views = {
  login:          document.getElementById('view-login'),
  register:       document.getElementById('view-register'),
  forgot:         document.getElementById('view-forgot'),
  reset:          document.getElementById('view-reset'),
  changePassword: document.getElementById('view-change-password'),
  dashboard:      document.getElementById('view-dashboard'),
  deleteAccount:  document.getElementById('view-delete-account'),
};

function showView(name) {
  Object.values(views).forEach(v => v && v.classList.remove('active'));
  if (views[name]) views[name].classList.add('active');
}
```

Alle Views liegen im DOM, nur die aktive hat die Klasse `.active` (in CSS: `display: block`). Navigation über `data-goto`-Attribute:

```javascript
// Alle Elemente mit data-goto registrieren
document.querySelectorAll('[data-goto]').forEach(el => {
  el.addEventListener('click', () => showView(el.dataset.goto));
});
```

### 4.3 Session-Check beim Seitenaufruf (Z. 86–125)

```javascript
let currentUser = null;

async function checkSession() {
  try {
    const params = new URLSearchParams(window.location.search);
    const data = await apiCall('me', null, 'GET');

    if (data.success && params.get('view') === 'account') {
      // Eingeloggt + ?view=account → Konto-Dashboard zeigen
      currentUser = data.data.user;
      renderDashboard();
      showView('dashboard');
    } else if (data.success && params.get('view') !== 'register') {
      // Eingeloggt ohne speziellen View → direkt zum Admin-Dashboard
      window.location.href = '../admin/dashboard.php';
      return;
    } else {
      showView('login');
    }
  } catch {
    showView('login');
  }
}
```

### 4.4 Dashboard rendern (Z. 127–134)

```javascript
function renderDashboard() {
  document.getElementById('dash-username').textContent = currentUser.username;
  document.getElementById('dash-email').textContent    = currentUser.email;

  // Test-Nutzer darf Passwort nicht ändern / Konto nicht löschen
  const isTester = currentUser.username === 'tester';
  document.querySelector('[data-goto="changePassword"]').style.display = isTester ? 'none' : '';
  document.querySelector('[data-goto="deleteAccount"]').style.display  = isTester ? 'none' : '';
}
```

### 4.5 Abmelden-Event-Handler (Z. 213–218)

```javascript
document.getElementById('btn-logout').addEventListener('click', async () => {
  await apiCall('logout');   // POST zu api.php?action=logout
  currentUser = null;        // Lokalen State leeren
  showView('login');         // Login-View anzeigen
});
```

---

## 5. Backend PHP: Login & Logout

### 5.1 API-Router (`auth/api.php`, Z. 28–49)

```php
$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? '');

// GET nur für /me, alles andere POST
if ($method === 'GET' && $action === 'me') {
    handleMe();
} elseif ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    switch ($action) {
        case 'login':  handleLogin($body);  break;
        case 'logout': handleLogout();      break;
        // ... weitere Actions
    }
}
```

### 5.2 Login (`auth/api.php`, Z. 130–212)

```php
function handleLogin(array $body): void {
    // Rate-Limit: 5 Versuche pro 5 Minuten (IP-basiert)
    if (!checkRateLimit('auth_login', 5, 300)) {
        apiError('Zu viele fehlgeschlagene Versuche. ...', 429);
    }

    $identifier = trim($body['identifier'] ?? ''); // username oder E-Mail
    $password   = $body['password'] ?? '';

    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT * FROM users
        WHERE (username = ? OR email = ?) AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    // Timing-sicherer Vergleich (immer password_verify aufrufen, auch bei nicht gefundenem Nutzer)
    $dummyHash = '$2y$12$invaliddummyhashfortimingatk.xxxxxxxxxxxxxxxxxxxxxxxxxx';
    $valid = $user && password_verify($password, $user['password_hash'] ?? $dummyHash);

    if (!$valid) {
        incrementRateLimit('auth_login');
        apiError('Ungültige Anmeldedaten.', 401);
    }

    // E-Mail muss verifiziert sein
    if ($user && empty($user['is_email_verified'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'email_unverified' => true, ...]);
        exit;
    }

    // Session regenerieren (Schutz gegen Session-Fixation)
    session_regenerate_id(true);

    // User-Session setzen
    $_SESSION[USER_SESSION_NAME]  = true;      // 'fs_user'
    $_SESSION['user_id']          = $user['id'];
    $_SESSION['user_username']    = $user['username'];
    $_SESSION['user_email']       = $user['email'];
    $_SESSION['last_activity']    = time();

    // Admin-Session mitsetzen (für admin/dashboard.php)
    $_SESSION[ADMIN_SESSION_NAME] = true;      // 'netzdiagramm_admin'
    $_SESSION['admin_id']         = $adminId;

    apiSuccess('Login erfolgreich.', ['user' => [...]]);
}
```

### 5.3 Logout via API (`auth/api.php`, Z. 214–222)

```php
function handleLogout(): void {
    $userId = $_SESSION['user_id'] ?? null;
    logSecurityEvent('logout', '', $userId);

    session_unset();
    session_destroy();
    apiSuccess('Erfolgreich abgemeldet.');
}
```

### 5.4 Logout via PHP-Redirect (`admin/logout.php`)

Wird aufgerufen wenn im Admin-Dashboard auf "Abmelden" geklickt wird (direkter Link):

```php
<?php
require_once '../config.php';
setSecurityHeaders();

// 1. Alle Session-Variablen löschen
$_SESSION = array();

// 2. Session-Cookie löschen (wichtig für vollständigen Logout)
if (isset($_COOKIE[session_name()])) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000, // Cookie in der Vergangenheit = löschen
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Session zerstören
session_destroy();

// Zurück zur Startseite
header('Location: ../index.php?logged_out=1');
exit;
```

**Unterschied zu API-Logout:** `logout.php` löscht explizit das Session-Cookie – der gründlichere Ansatz.

---

## 6. Session-Management

### 6.1 Zwei Session-Systeme

Das Projekt nutzt zwei parallele Session-Flags:

| Konstante | Wert | Zweck | Timeout |
|---|---|---|---|
| `USER_SESSION_NAME` | `'fs_user'` | Allgemeine User-Auth (auth/api.php) | 30 Min |
| `ADMIN_SESSION_NAME` | `'netzdiagramm_admin'` | Zugang zum Admin-Dashboard | 60 Min |

Beide werden beim Login gleichzeitig gesetzt (siehe `handleLogin()`).

### 6.2 Session-Konstanten (`config.php`, Z. 52–53)

```php
define('ADMIN_SESSION_NAME', 'netzdiagramm_admin');
define('SESSION_TIMEOUT', 3600); // 1 Stunde
```

In `auth/api.php` (Z. 25–26):

```php
define('USER_SESSION_NAME', 'fs_user');
define('USER_SESSION_TIMEOUT', 1800); // 30 Minuten
```

### 6.3 Admin-Zugang prüfen (`config.php`, `requireAdmin()`)

Wird am Anfang jeder Admin-Seite aufgerufen:

```php
function requireAdmin() {
    // Fallback: User-Session → Admin-Session ableiten
    if (!isAdminLoggedIn()
        && isset($_SESSION['fs_user']) && $_SESSION['fs_user'] === true
        && isset($_SESSION['user_id'])) {
        $_SESSION[ADMIN_SESSION_NAME] = true;
        $_SESSION['admin_id']         = $_SESSION['user_id'];
    }

    if (!isAdminLoggedIn()) {
        header('Location: /admin/index.php');
        exit;
    }

    // Inaktivitäts-Timeout (1 Stunde)
    if (isset($_SESSION['last_activity'])) {
        $inactiveTime = time() - $_SESSION['last_activity'];
        if ($inactiveTime > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            header('Location: /admin/index.php');
            exit;
        }
    }

    $_SESSION['last_activity'] = time(); // Timestamp aktualisieren
}
```

### 6.4 Session-Check Frontend (`auth/app.js`, API-Endpoint `me`)

Der `me`-Endpoint prüft ob eine gültige Session existiert:

```php
// auth/api.php: handleMe()
// Prüft $_SESSION['user_id'] und gibt User-Daten zurück
// GET-Request zu: api.php?action=me
```

---

## 7. Sicherheitsfeatures

### 7.1 Rate Limiting

| Aktion | Limit | Zeitfenster |
|---|---|---|
| Login | 5 Versuche | 5 Minuten |
| Registrierung | 5 Versuche | 5 Minuten |
| Passwort-Reset | 3 Versuche | 15 Minuten |

### 7.2 Weitere Maßnahmen

- **Session Regeneration** bei jedem Login (`session_regenerate_id(true)`) – Schutz gegen Session-Fixation
- **Timing-sicherer Passwort-Vergleich** – `password_verify()` wird auch bei nicht gefundenen Nutzern aufgerufen (Dummy-Hash), um Timing-Angriffe zu verhindern
- **Bcrypt** mit Cost-Factor 12 für Passwort-Hashing
- **E-Mail-Verifikation** – Nutzer müssen E-Mail bestätigen bevor Login möglich ist
- **HttpOnly-Cookies** – Session-Cookie nicht per JavaScript zugreifbar
- **Cookie explizit löschen** beim Logout (in `admin/logout.php`)

---

## 8. Komplette Flows

### 8.1 Login → Admin-Dashboard

```
Nutzer gibt Credentials ein
        ↓
auth/app.js: apiCall('login', { identifier, password })
        ↓
auth/api.php: handleLogin()
  ├─ Rate-Limit prüfen
  ├─ User in DB suchen
  ├─ password_verify() (timing-sicher)
  ├─ E-Mail-Verifikation prüfen
  ├─ session_regenerate_id(true)
  ├─ $_SESSION['fs_user'] = true
  ├─ $_SESSION['user_id'], ['user_username'], ['user_email'], ['last_activity'] setzen
  └─ $_SESSION['netzdiagramm_admin'] = true, ['admin_id'] setzen
        ↓
JSON: { success: true, data: { user: {...} } }
        ↓
auth/app.js: window.location.href = '../admin/dashboard.php'
        ↓
admin/dashboard.php: requireAdmin() prüft Session
        ↓
Dashboard mit "Mein Konto" + "Abmelden" Buttons wird gerendert
```

### 8.2 "Mein Konto" aufrufen

```
Nutzer klickt "Mein Konto" im Admin-Dashboard
        ↓
Navigiert zu: /auth/?view=account
        ↓
auth/app.js: checkSession() → apiCall('me', null, 'GET')
        ↓
auth/api.php: handleMe() → gibt User-Daten zurück (falls Session gültig)
        ↓
auth/app.js: currentUser = data.data.user
              renderDashboard() → befüllt #dash-username, #dash-email
              showView('dashboard') → zeigt #view-dashboard
        ↓
Konto-Dashboard sichtbar mit "Abmelden"-Button
```

### 8.3 Logout aus dem Konto-Dashboard (JS-Flow)

```
Nutzer klickt "Abmelden" (#btn-logout)
        ↓
auth/app.js: apiCall('logout') → POST zu api.php?action=logout
        ↓
auth/api.php: handleLogout()
  ├─ logSecurityEvent('logout', ...)
  ├─ session_unset()
  └─ session_destroy()
        ↓
auth/app.js: currentUser = null
              showView('login')
        ↓
Login-View wird angezeigt (kein Redirect)
```

### 8.4 Logout aus dem Admin-Dashboard (PHP-Flow)

```
Nutzer klickt "Abmelden" im Admin-Dashboard-Header
        ↓
Navigiert zu: admin/logout.php
        ↓
admin/logout.php:
  ├─ $_SESSION = array()           (alle Variablen löschen)
  ├─ setcookie(..., time()-42000)  (Cookie löschen)
  └─ session_destroy()
        ↓
header('Location: ../index.php?logged_out=1')
        ↓
Startseite mit Logout-Bestätigung
```

---

## 9. Dateistruktur

```
/
├── auth/
│   ├── index.html     # Auth-UI: alle Views (Login, Register, Konto-Dashboard)
│   ├── app.js         # Client-seitige Logik: apiCall(), showView(), Event-Handler
│   ├── styles.css     # Alle Styles für Auth-Seiten inkl. Button-Klassen
│   └── api.php        # PHP-API: handleLogin(), handleLogout(), handleMe(), ...
├── admin/
│   ├── dashboard.php  # Admin-Dashboard mit "Mein Konto" + "Abmelden" im Header
│   └── logout.php     # Server-seitiger Logout (Session + Cookie löschen)
└── config.php         # Session-Konstanten, requireAdmin(), isAdminLoggedIn()
```

### Schlüssel-Referenzen

| Was | Datei | Zeile(n) |
|---|---|---|
| "Mein Konto" Button (Admin-Header) | `admin/dashboard.php` | 392 |
| "Abmelden" Button (Admin-Header) | `admin/dashboard.php` | 393 |
| "Abmelden" Button (Konto-Dashboard) | `auth/index.html` | 222 |
| Konto-Dashboard HTML | `auth/index.html` | 209–229 |
| `apiCall()` Funktion | `auth/app.js` | 10–20 |
| `showView()` / View-Router | `auth/app.js` | 22–36 |
| `checkSession()` | `auth/app.js` | 86–125 |
| `renderDashboard()` | `auth/app.js` | 127–134 |
| Logout-Event-Handler | `auth/app.js` | 213–218 |
| Button-CSS | `auth/styles.css` | 124–153 |
| CSS-Variablen | `auth/styles.css` | 3–20 |
| `handleLogin()` PHP | `auth/api.php` | 130–212 |
| `handleLogout()` PHP | `auth/api.php` | 214–222 |
| Session-Konstanten | `config.php` | 52–53 |
| `requireAdmin()` | `config.php` | 625–654 |
| Logout (PHP-Redirect) | `admin/logout.php` | 1–36 |
