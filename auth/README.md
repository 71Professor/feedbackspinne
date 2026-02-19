# Feedbackspinne – Nutzerverwaltung (Auth-System)

Vollständiges User-Authentifizierungs-System für die Feedbackspinne-Webanwendung.

## Funktionsübersicht

| Funktion | Beschreibung |
|---|---|
| Selbstregistrierung | Username, E-Mail, Passwort (mit Komplexitätsprüfung) |
| Login / Logout | Session-basiert, HttpOnly-Cookies, 30 Min. Timeout |
| Passwort vergessen | Sicherer Reset-Link per E-Mail (60 Min. gültig) |
| Passwort zurücksetzen | Via E-Mail-Token |
| Passwort ändern | In den Benutzer-Einstellungen |
| Security-Logging | Alle relevanten Events in der DB protokolliert |

## Dateistruktur

```
feedbackspinne/
├── auth/
│   ├── api.php          ← REST-API (alle Endpoints)
│   ├── mailer.php       ← PHPMailer-Wrapper
│   ├── logger.php       ← Security-Event-Logging
│   ├── setup.php        ← DB-Schema einrichten
│   ├── test_auth.php    ← Test-Skript (CLI)
│   ├── index.html       ← Frontend-SPA (alle Formulare)
│   ├── app.js           ← API-Client + Event-Handler
│   ├── styles.css       ← Responsive Design
│   ├── README.md        ← Diese Datei
│   └── DEPLOYMENT.md   ← Deployment-Checkliste
├── config.php           ← Zentrale DB + Session Config
├── composer.json        ← PHPMailer-Dependency
├── .env.example         ← Config-Template
├── .htaccess            ← Apache-Security
└── .gitignore           ← Secrets ausschließen
```

## Voraussetzungen

- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Composer
- PHP-Extensions: `pdo_mysql`, `mbstring`, `openssl`, `curl`
- Apache mit `mod_rewrite` und `mod_headers`
- SMTP-Zugang (z. B. Mailgun, SendGrid, eigener Server)

## Installation

### 1. Composer-Dependencies installieren

```bash
cd /pfad/zur/feedbackspinne
composer install
```

### 2. `.env`-Datei konfigurieren

```bash
cp .env.example .env
nano .env
```

Pflichtfelder ausfüllen:

```ini
# Datenbank
DB_HOST=localhost
DB_NAME=feedbackspinne
DB_USER=dbuser
DB_PASS=sicheres_passwort

# Sicherheit
SECURE_KEY=$(openssl rand -base64 32)

# App
APP_URL=https://deine-domain.de

# SMTP
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=noreply@deine-domain.de
SMTP_PASS=smtp_passwort
SMTP_FROM_EMAIL=noreply@deine-domain.de
SMTP_FROM_NAME=Feedbackspinne
```

### 3. Datenbank-Tabellen anlegen

```bash
php auth/setup.php
```

Ausgabe bei Erfolg:
```
✓ Tabelle 'users' bereit.
✓ Tabelle 'password_reset_tokens' bereit.
✓ Tabelle 'security_log' bereit.
✓ Tabelle 'rate_limits' bereits vorhanden.
Setup abgeschlossen. Alle Tabellen sind bereit.
```

### 4. Testen

```bash
php auth/test_auth.php
```

### 5. Auth-Frontend aufrufen

Öffne im Browser: `https://deine-domain.de/auth/`

## REST-API-Referenz

Basis-URL: `/auth/api.php`

### POST `?action=register`

```json
{ "username": "max", "email": "max@example.com", "password": "Secure#99!" }
```

### POST `?action=login`

```json
{ "identifier": "max", "password": "Secure#99!" }
```

(identifier = Username **oder** E-Mail)

### POST `?action=logout`

Kein Body erforderlich.

### POST `?action=forgot_password`

```json
{ "email": "max@example.com" }
```

Antwort immer `success: true` (Anti-Enumeration).

### POST `?action=reset_password`

```json
{ "token": "<raw-token-aus-email>", "password": "NewPass#99!" }
```

### POST `?action=change_password`

Erfordert aktive Session.

```json
{ "old_password": "Secure#99!", "new_password": "NewPass#99!" }
```

### GET `?action=me`

Gibt die Daten des eingeloggten Users zurück (oder 401).

## Passwort-Komplexität

Passwörter müssen folgende Anforderungen erfüllen:
- Mindestens **8 Zeichen**
- Mindestens **ein Großbuchstabe** (A–Z)
- Mindestens **ein Kleinbuchstabe** (a–z)
- Mindestens **eine Ziffer** (0–9)
- Mindestens **ein Sonderzeichen** (`!@#$%^&*` etc.)

## Security-Features

| Feature | Details |
|---|---|
| Passwort-Hashing | `password_hash()` mit BCRYPT, cost=12 |
| Session-Timeout | 30 Minuten Inaktivität |
| Rate-Limiting | DB-basiert, IP-gebunden |
| Login | 5 Versuche / 5 Min |
| Register | 5 Versuche / 5 Min |
| Forgot | 3 Anfragen / 15 Min |
| HttpOnly-Cookies | Ja |
| SameSite | Strict (PHP `session.cookie_samesite`) |
| CSRF-Schutz | CSRF-Token in allen Formularen |
| Anti-Enumeration | Forgot-Password gibt immer Erfolg zurück |
| Session-Regeneration | `session_regenerate_id(true)` nach Login |
| Prepared Statements | Alle DB-Queries mit PDO Prepared Statements |
| Security-Logging | Events in `security_log`-Tabelle |

## Troubleshooting

**`vendor/autoload.php` nicht gefunden**
→ `composer install` im Root-Verzeichnis ausführen.

**E-Mails kommen nicht an**
→ SMTP-Credentials in `.env` prüfen; `DEBUG_MODE=true` setzen und Mailer-Ausgabe prüfen.

**Rate-Limit beim Testen**
→ `DELETE FROM rate_limits WHERE limit_key LIKE 'auth_%';` in der DB ausführen.

**Session-Timeout zu kurz/lang**
→ Konstante `USER_SESSION_TIMEOUT` in `auth/api.php` anpassen (Default: 1800 Sekunden).
