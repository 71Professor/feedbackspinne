# Feature: "Mein Konto"

Dieses Dokument beschreibt vollständig, was der "Mein Konto"-Bereich kann und wie er implementiert ist. Es dient als Anleitung zur Umsetzung in einem anderen Projekt.

---

## Was "Mein Konto" zeigt und kann

```
┌─────────────────────────────────────┐
│  Mein Konto                         │
│  Eingeloggt als:                    │
│                                     │
│  ┌─────────────────────────────┐    │
│  │ KSchule                     │    │  ← Nutzerinfo-Box (grün)
│  │ kohlschule@gmail.com        │    │
│  └─────────────────────────────┘    │
│                                     │
│  [ 🔑 Passwort ändern ]             │  ← ghost-Button (grüner Rahmen)
│  [       Abmelden       ]           │  ← primär-Button (grün gefüllt)
│  [    Konto löschen     ]           │  ← ghost-Button (roter Rahmen)
│                                     │
│  ──────── oder ────────             │
│                                     │
│  [ ← Zur Feedbackspinne ]          │  ← ghost-Link (grüner Rahmen)
└─────────────────────────────────────┘
```

| Aktion | Verhalten |
|---|---|
| Nutzerinfo-Box | Zeigt Benutzername + E-Mail, wird per JS befüllt |
| Passwort ändern | Wechselt zu eigenem Unterformular (View-System, kein Redirect) |
| Abmelden | API-Call → Session zerstören → Login-View anzeigen |
| Konto löschen | Wechselt zu Unterformular mit Passwortbestätigung + Browser-Confirm |
| ← Zur Feedbackspinne | Link zurück zum Admin-Dashboard |

---

## Einstiegspunkt

Die Seite wird aufgerufen über: `/auth/?view=account`

Beim Laden prüft `checkSession()` (`auth/app.js`, Z. 86–125) die Session:

```javascript
async function checkSession() {
  const params = new URLSearchParams(window.location.search);
  const data = await apiCall('me', null, 'GET');  // GET api.php?action=me

  if (data.success && params.get('view') === 'account') {
    // Eingeloggt + ?view=account → Konto-Dashboard zeigen
    currentUser = data.data.user;
    renderDashboard();
    showView('dashboard');
  } else if (data.success) {
    // Eingeloggt, aber ohne view=account → weiterleiten
    window.location.href = '../admin/dashboard.php';
  } else {
    showView('login');
  }
}
```

---

## View-System

Alle Screens (Dashboard, Passwort ändern, Konto löschen) sind **bereits im DOM** und werden per CSS ein-/ausgeblendet. Kein Redirect, kein React – reines Vanilla JS.

```css
/* auth/styles.css */
.auth-view        { display: none; }
.auth-view.active { display: block; }
```

```javascript
// auth/app.js – View-Router (Z. 22–36)
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

Navigation zwischen Views über `data-goto`-Attribute (kein `id`, kein JS im HTML):

```javascript
// Registriert alle data-goto-Buttons (Z. 332–335)
document.querySelectorAll('[data-goto]').forEach(el => {
  el.addEventListener('click', () => showView(el.dataset.goto));
});
```

---

## 1. Nutzerinfo-Box + Dashboard rendern

### HTML (`auth/index.html`, Z. 209–229)

```html
<div id="view-dashboard" class="card auth-view">
  <h2>Mein Konto</h2>
  <p class="subtitle">Eingeloggt als:</p>

  <!-- Nutzer-Info (per JS befüllt) -->
  <div class="user-bar">
    <div>
      <div class="name"  id="dash-username"></div>
      <div class="email" id="dash-email"></div>
    </div>
  </div>

  <!-- Aktions-Buttons -->
  <div style="display:flex;flex-direction:column;gap:12px;">
    <button class="btn btn-ghost" data-goto="changePassword">🔑 Passwort ändern</button>
    <button id="btn-logout" class="btn btn-primary">Abmelden</button>
    <button class="btn btn-ghost" style="color:#dc2626;border-color:#dc2626;" data-goto="deleteAccount">Konto löschen</button>
  </div>

  <div class="divider">oder</div>

  <a href="../admin/dashboard.php" class="btn btn-ghost">← Zur Feedbackspinne</a>
</div>
```

### JavaScript – Dashboard befüllen (`auth/app.js`, Z. 127–134)

```javascript
let currentUser = null;

function renderDashboard() {
  document.getElementById('dash-username').textContent = currentUser.username;
  document.getElementById('dash-email').textContent    = currentUser.email;

  // Sonderfall: Test-Nutzer darf Passwort nicht ändern / Konto nicht löschen
  const isTester = currentUser.username === 'tester';
  document.querySelector('[data-goto="changePassword"]').style.display = isTester ? 'none' : '';
  document.querySelector('[data-goto="deleteAccount"]').style.display  = isTester ? 'none' : '';
}
```

---

## 2. Aktion: Passwort ändern

### HTML (`auth/index.html`, Z. 174–204)

```html
<div id="view-change-password" class="card auth-view">
  <h2>Passwort ändern</h2>
  <p class="subtitle">Aktuelles und neues Passwort eingeben.</p>

  <div id="change-alert" class="alert"></div>

  <form id="form-change-password" novalidate>
    <div class="form-group">
      <label for="change-old-password">Aktuelles Passwort</label>
      <input type="password" id="change-old-password" name="old_password"
             autocomplete="current-password" required placeholder="••••••••">
    </div>
    <div class="form-group">
      <label for="change-new-password">Neues Passwort</label>
      <input type="password" id="change-new-password" name="new_password"
             autocomplete="new-password" required placeholder="••••••••">
      <!-- Passwort-Stärke-Anzeige -->
      <div class="pw-meter"><div class="pw-meter-fill" id="change-pw-fill" style="width:0"></div></div>
      <div class="pw-hint">Min. 8 Zeichen, Groß- &amp; Kleinbuchstabe, Zahl und Sonderzeichen.</div>
    </div>
    <div class="form-group">
      <label for="change-confirm-password">Neues Passwort bestätigen</label>
      <input type="password" id="change-confirm-password" name="confirm_new_password"
             autocomplete="new-password" required placeholder="••••••••">
    </div>
    <button type="submit" class="btn btn-primary">Passwort ändern</button>
  </form>

  <div class="nav-links">
    <button class="nav-link" data-goto="dashboard">← Zurück</button>
  </div>
</div>
```

### JavaScript (`auth/app.js`, Z. 274–305)

```javascript
document.getElementById('form-change-password').addEventListener('submit', async e => {
  e.preventDefault();
  clearAlert('change-alert');

  if (e.target.new_password.value !== e.target.confirm_new_password.value) {
    showAlert('change-alert', 'Die neuen Passwörter stimmen nicht überein.', 'error');
    return;
  }

  const btn = e.target.querySelector('button[type=submit]');
  setLoading(btn, true);

  const res = await apiCall('change_password', {
    old_password: e.target.old_password.value,
    new_password: e.target.new_password.value,
  });

  setLoading(btn, false);
  if (res.success) {
    showAlert('change-alert', res.message, 'success');
    e.target.reset();
    updateMeter('', 'change-pw-fill');
    setTimeout(() => showView('dashboard'), 2000);  // nach 2s zurück zum Dashboard
  } else {
    showAlert('change-alert', res.message, 'error');
  }
});

// Passwort-Stärke-Meter live aktualisieren
document.getElementById('change-new-password').addEventListener('input', function () {
  updateMeter(this.value, 'change-pw-fill');
});
```

### Passwort-Stärke-Meter (`auth/app.js`, Z. 63–81)

```javascript
function passwordStrength(pw) {
  let score = 0;
  if (pw.length >= 8)   score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[a-z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[\W_]/.test(pw)) score++;
  return score; // 0–5
}

function updateMeter(pw, fillId) {
  const fill  = document.getElementById(fillId);
  if (!fill) return;
  const s     = passwordStrength(pw);
  const color = s < 2 ? '#dc2626' : s < 4 ? '#f59e0b' : '#7ab800';
  fill.style.width      = (s * 20) + '%';
  fill.style.background = color;
}
```

---

## 3. Aktion: Abmelden

### HTML (`auth/index.html`, Z. 222)

```html
<button id="btn-logout" class="btn btn-primary">Abmelden</button>
```

### JavaScript (`auth/app.js`, Z. 213–218)

```javascript
document.getElementById('btn-logout').addEventListener('click', async () => {
  await apiCall('logout');   // POST zu api.php?action=logout
  currentUser = null;        // Lokalen State leeren
  showView('login');         // Login-View anzeigen (kein Redirect)
});
```

### PHP Backend (`auth/api.php`, Z. 214–222)

```php
function handleLogout(): void {
    $userId = $_SESSION['user_id'] ?? null;
    logSecurityEvent('logout', '', $userId);

    session_unset();    // Alle Session-Variablen löschen
    session_destroy();  // Session zerstören
    apiSuccess('Erfolgreich abgemeldet.');
}
```

---

## 4. Aktion: Konto löschen

### HTML (`auth/index.html`, Z. 234–258)

```html
<div id="view-delete-account" class="card auth-view">
  <h2>Konto löschen</h2>
  <p class="subtitle" style="color:#dc2626;">
    Diese Aktion ist unwiderruflich. Alle deine Daten, Sessions und
    Feedback-Einreichungen werden permanent gelöscht.
  </p>

  <div id="delete-alert" class="alert"></div>

  <form id="form-delete-account" novalidate>
    <div class="form-group">
      <label for="delete-password">Passwort zur Bestätigung</label>
      <input type="password" id="delete-password" name="password"
             autocomplete="current-password" required placeholder="••••••••">
    </div>
    <button type="submit" class="btn btn-primary"
            style="background:#dc2626;border-color:#dc2626;">
      Konto unwiderruflich löschen
    </button>
  </form>

  <div class="nav-links">
    <button class="nav-link" data-goto="dashboard">← Zurück</button>
  </div>
</div>
```

### JavaScript (`auth/app.js`, Z. 307–330)

```javascript
document.getElementById('form-delete-account').addEventListener('submit', async e => {
  e.preventDefault();
  clearAlert('delete-alert');

  // Zusätzliche Browser-Bestätigung
  if (!confirm('Bist du sicher? Diese Aktion kann nicht rückgängig gemacht werden.')) return;

  const btn = e.target.querySelector('button[type=submit]');
  setLoading(btn, true);

  const res = await apiCall('delete_account', {
    password: e.target.password.value,
  });

  setLoading(btn, false);
  if (res.success) {
    showAlert('delete-alert', res.message, 'success');
    currentUser = null;
    e.target.reset();
    setTimeout(() => showView('login'), 2500);  // nach 2,5s zur Login-View
  } else {
    showAlert('delete-alert', res.message, 'error');
  }
});
```

---

## 5. Navigation "← Zur Feedbackspinne"

```html
<!-- Einfacher Anchor-Tag, kein JS -->
<a href="../admin/dashboard.php" class="btn btn-ghost">← Zur Feedbackspinne</a>
```

---

## Styling

Alle Styles in `auth/styles.css`.

### CSS-Variablen (Z. 3–20)

```css
:root {
  --green:    #7ab800;   /* Haupt-Grün */
  --green-2:  #5e9800;   /* Dunkleres Grün (Hover) */
  --green-bg: rgba(122,184,0,.08);  /* Sehr helles Grün (Nutzerbox-Hintergrund) */
  --red:      #dc2626;
  --text:     #0f172a;
  --muted:    #64748b;
  --border:   #e5e7eb;
  --radius:   16px;
  --trans:    .18s ease;
}
```

### Button-Klassen (Z. 124–153)

```css
/* Basis – gilt für alle Buttons */
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

/* Gefüllt – "Abmelden"-Button */
.btn-primary {
  background: var(--green);
  color: #fff;
}
.btn-primary:hover:not(:disabled) { background: var(--green-2); }

/* Outline – "Passwort ändern", "← Zur Feedbackspinne" */
.btn-ghost {
  background: transparent;
  color: var(--green-2);
  border: 1.5px solid var(--green);
}
.btn-ghost:hover:not(:disabled) { background: var(--green-bg); }

/* "Konto löschen" – ghost mit roter Farbe (inline style überschreibt) */
/* style="color:#dc2626;border-color:#dc2626;" */
```

### Nutzerinfo-Box (Z. 198–217)

```css
.user-bar {
  background: var(--green-bg);                   /* Sehr heller Grün-Hintergrund */
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

### "oder"-Divider (Z. 236–244)

```css
.divider {
  display: flex; align-items: center; gap: 12px;
  margin: 22px 0;
  color: var(--muted); font-size: 12px;
}
.divider::before, .divider::after {
  content: ''; flex: 1; border-top: 1px solid var(--border);
}
```

---

## Hilfs-Funktionen (werden überall verwendet)

```javascript
// Button in Lade-Zustand versetzen (Spinner anzeigen)
function setLoading(btn, loading) {
  if (loading) {
    btn.dataset.origText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span>';
    btn.disabled  = true;
  } else {
    btn.innerHTML = btn.dataset.origText || btn.innerHTML;
    btn.disabled  = false;
  }
}

// Alert-Box anzeigen/löschen
function showAlert(id, msg, type = 'error') {
  const el = document.getElementById(id);
  el.textContent = msg;
  el.className   = `alert alert-${type} show`;
}
function clearAlert(id) {
  const el = document.getElementById(id);
  if (el) { el.textContent = ''; el.classList.remove('show'); }
}

// API-Wrapper (alle Calls laufen hierüber)
async function apiCall(action, body = null, method = 'POST') {
  const opts = {
    method,
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',  // Session-Cookie mitsenden!
  };
  if (body) opts.body = JSON.stringify(body);
  const res = await fetch(`api.php?action=${action}`, opts);
  return res.json();
}
```

---

## Datei-Referenzen

| Datei | Zeilen | Inhalt |
|---|---|---|
| `auth/index.html` | 174–204 | View: Passwort ändern |
| `auth/index.html` | 209–229 | View: Mein Konto (Dashboard) |
| `auth/index.html` | 234–258 | View: Konto löschen |
| `auth/app.js` | 10–20 | `apiCall()` |
| `auth/app.js` | 22–36 | View-Router, `showView()` |
| `auth/app.js` | 51–60 | `setLoading()` |
| `auth/app.js` | 63–81 | Passwort-Stärke-Meter |
| `auth/app.js` | 86–125 | `checkSession()` |
| `auth/app.js` | 127–134 | `renderDashboard()` |
| `auth/app.js` | 213–218 | Logout-Event-Handler |
| `auth/app.js` | 274–305 | Change-Password-Handler |
| `auth/app.js` | 307–330 | Delete-Account-Handler |
| `auth/app.js` | 332–335 | `data-goto`-Navigation |
| `auth/styles.css` | 3–20 | CSS-Variablen |
| `auth/styles.css` | 124–153 | Button-Klassen |
| `auth/styles.css` | 195–244 | user-bar, divider |
| `auth/api.php` | 214–222 | `handleLogout()` |
