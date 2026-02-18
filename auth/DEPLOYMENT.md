# Feedbackspinne – Auth-System Deployment-Checkliste

Vor dem Go-Live diese Checkliste vollständig abarbeiten.

---

## ☑ Vorbereitung

- [ ] Server erfüllt Mindestanforderungen: PHP ≥ 7.4, MySQL ≥ 5.7, Apache mit mod_rewrite/mod_headers
- [ ] HTTPS-Zertifikat installiert und gültig (Let's Encrypt o. ä.)
- [ ] `composer install --no-dev --optimize-autoloader` ausgeführt
- [ ] `.env` aus `.env.example` kopiert und **alle Werte befüllt**

---

## ☑ .env – Produktionswerte

- [ ] `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` → produktive Datenbank
- [ ] `SECURE_KEY` → 32+ Byte zufälliger String (`openssl rand -base64 32`)
- [ ] `DEBUG_MODE=false` → niemals `true` in Produktion!
- [ ] `APP_URL` → exakte HTTPS-URL ohne abschließenden Slash
- [ ] `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` → verifiziert
- [ ] `SMTP_FROM_EMAIL` → Domain-verifizierte Absenderadresse (SPF/DKIM)

---

## ☑ Datenbank

- [ ] Datenbank und Nutzer angelegt, Nutzer hat nur notwendige Rechte (SELECT, INSERT, UPDATE, DELETE auf die jeweiligen Tabellen)
- [ ] `php auth/setup.php` erfolgreich ausgeführt
- [ ] Alle vier Tabellen vorhanden: `users`, `password_reset_tokens`, `security_log`, `rate_limits`
- [ ] Regelmäßige DB-Backups konfiguriert

---

## ☑ Apache / Webserver

- [ ] `.htaccess` aktiv (AllowOverride All in Apache-Config)
- [ ] HTTPS-Redirect aktiv (Zeilen in `.htaccess` nicht auskommentiert)
- [ ] Zugriff auf `.env`, `composer.json`, `*.sql`, `setup.php` gesperrt → Test: `curl https://domain.de/.env` → muss 403/404 liefern
- [ ] Directory Listing deaktiviert (`Options -Indexes`)
- [ ] Security-Header gesetzt → Test mit [securityheaders.com](https://securityheaders.com)

---

## ☑ SMTP / E-Mail

- [ ] SPF-Record für Absender-Domain gesetzt
- [ ] DKIM konfiguriert
- [ ] Test-E-Mail über `php -r "require 'config.php'; require 'auth/mailer.php'; var_dump(sendPasswordResetEmail('test@example.com','Test','dummytoken'));"` erfolgreich
- [ ] E-Mail landet nicht im Spam

---

## ☑ Session & Cookies

- [ ] `session.cookie_secure = 1` aktiv (automatisch wenn HTTPS erkannt)
- [ ] `session.cookie_httponly = 1` aktiv
- [ ] `session.cookie_samesite = Lax` aktiv
- [ ] Session-Timeout auf sinnvollen Wert gesetzt (`USER_SESSION_TIMEOUT` in `auth/api.php`)

---

## ☑ Sicherheits-Tests

- [ ] Rate-Limit funktioniert: 5 Fehllogins → gesperrt
- [ ] Forgot-Password-Endpoint gibt bei unbekannter E-Mail dasselbe Ergebnis wie bei bekannter zurück (Anti-Enumeration)
- [ ] Reset-Token ist nach Nutzung ungültig
- [ ] Reset-Token läuft nach 60 Minuten ab
- [ ] `php auth/test_auth.php` → alle Tests bestanden (0 fehlgeschlagen)
- [ ] OWASP ZAP oder ähnliches Tool gegen Login-Endpunkte ausgeführt

---

## ☑ Monitoring & Wartung

- [ ] `security_log`-Tabelle wird regelmäßig auf Anomalien geprüft
- [ ] Cron-Job für `cron_cleanup_rate_limits.php` eingerichtet (bereinigt alte Rate-Limit-Einträge)
- [ ] Cron-Job für abgelaufene Reset-Tokens: `DELETE FROM password_reset_tokens WHERE expires_at < NOW() AND used_at IS NULL;`
- [ ] PHP-Error-Log überwacht (kein `display_errors` in Produktion)
- [ ] Dependency-Updates geplant (`composer outdated`)

---

## ☑ Post-Deploy-Verifikation

- [ ] Registrierung funktioniert (echter Browser)
- [ ] Login / Logout funktioniert
- [ ] "Passwort vergessen" sendet E-Mail (prüfe Posteingang + Spam)
- [ ] Reset-Link in E-Mail funktioniert
- [ ] Passwort ändern in Einstellungen funktioniert
- [ ] 30-Minuten-Timeout: nach Inaktivität wird man ausgeloggt

---

## Empfohlene Cron-Jobs

```cron
# Täglich um 3 Uhr: abgelaufene Reset-Tokens löschen
0 3 * * * php /var/www/feedbackspinne/auth/setup.php 2>/dev/null
# (oder direktes SQL via cron)

# Stündlich: Rate-Limit-Einträge bereinigen
0 * * * * php /var/www/feedbackspinne/cron_cleanup_rate_limits.php
```

---

*Stand: 2026-02 – Feedbackspinne Auth-System v1.0*
