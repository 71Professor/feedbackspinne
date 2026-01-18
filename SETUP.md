# Feedbackspinne - Setup-Anleitung

## 📋 Ersteinrichtung

### 1. Umgebungsvariablen konfigurieren

```bash
# .env.example zu .env kopieren
cp .env.example .env

# .env mit echten Credentials bearbeiten
nano .env
```

Fülle folgende Werte in der `.env`-Datei aus:

```env
DB_HOST=localhost              # Datenbank-Host
DB_NAME=deine_datenbank       # Datenbank-Name
DB_USER=dein_benutzer         # Datenbank-Benutzer
DB_PASS=dein_sicheres_passwort # Datenbank-Passwort
SECURE_KEY=generiere_zufaelligen_string  # z.B. mit: openssl rand -base64 32
DEBUG_MODE=false              # true nur für Entwicklung
```

### 2. Datenbank-Migration durchführen

```bash
# Migration ausführen (siehe MIGRATION_ANLEITUNG.md)
mysql -u BENUTZER -p DATENBANK < migration_add_session_ownership.sql
```

### 3. Admin-Account erstellen

#### Standard-Admin-Credentials (NUR für Ersteinrichtung!)

**⚠️ WICHTIG: Diese Credentials SOFORT nach dem ersten Login ändern!**

```
Benutzername: admin
Passwort: admin123
```

#### Passwort ändern (PFLICHT!)

Nach dem ersten Login:

1. In der Datenbank mit phpMyAdmin oder CLI einloggen
2. Neues Passwort-Hash generieren:

```php
<?php
// Einmalig ausführen, um neues Passwort zu generieren
echo password_hash('DEIN_NEUES_SICHERES_PASSWORT', PASSWORD_DEFAULT);
?>
```

3. Admin-Passwort in Datenbank aktualisieren:

```sql
UPDATE admin_users
SET password_hash = '$2y$10$DEIN_GENERIERTER_HASH_HIER'
WHERE username = 'admin';
```

**Alternativ:** Nutze das mitgelieferte Passwort-Change-Script (falls vorhanden) oder implementiere eine Passwort-Änderungs-Funktion im Admin-Panel.

### 4. Sicherheits-Checkliste nach Setup

- [ ] `.env`-Datei mit echten Credentials erstellt
- [ ] `.env` ist NICHT öffentlich zugänglich (außerhalb des Webroot oder durch .htaccess geschützt)
- [ ] Standard-Admin-Passwort geändert
- [ ] HTTPS aktiviert (Let's Encrypt empfohlen)
- [ ] `DEBUG_MODE=false` in Produktion
- [ ] Datenbank-Backups eingerichtet
- [ ] Datenschutzerklärung erstellt (DSGVO)

### 5. Ordnerberechtigungen (empfohlen)

```bash
# Dateien: 644, Ordner: 755
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# .env nur für Webserver lesbar
chmod 600 .env
```

### 6. Webserver-Konfiguration

#### Apache (.htaccess)

Erstelle `.htaccess` im Root-Verzeichnis:

```apache
# .env vor Zugriff schützen
<Files ".env">
    Require all denied
</Files>

# HTTPS erzwingen (wenn SSL verfügbar)
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

#### Nginx

```nginx
# .env blockieren
location ~ /\.env {
    deny all;
    return 404;
}

# HTTPS erzwingen
server {
    listen 80;
    server_name example.com;
    return 301 https://$server_name$request_uri;
}
```

## 🚀 Deployment

### Produktiv-Server (z.B. all-inkl.com)

1. Dateien via FTP/SFTP hochladen
2. `.env` auf dem Server erstellen (NICHT hochladen aus Git!)
3. Datenbank-Migration durchführen
4. Ersten Login mit Standard-Credentials
5. **SOFORT** Passwort ändern
6. SSL/HTTPS aktivieren

### Entwicklungs-Server (lokal)

```bash
# PHP eingebauter Webserver (nur Entwicklung!)
php -S localhost:8000

# Öffne: http://localhost:8000/admin
```

## 🔒 Sicherheitshinweise

1. **Standard-Credentials:** Werden nur bei DB-Setup automatisch angelegt. Ändern Sie diese sofort!
2. **SECURE_KEY:** Generieren Sie einen zufälligen String (nicht den aus .env.example verwenden!)
3. **Backups:** Richten Sie automatische Datenbank-Backups ein
4. **Updates:** Halten Sie PHP und MySQL aktuell
5. **Logs:** Überwachen Sie Logs auf ungewöhnliche Aktivitäten

## 📞 Support

Bei Problemen siehe:
- `SECURITY_AUDIT.md` - Sicherheitsempfehlungen
- `MIGRATION_ANLEITUNG.md` - Datenbank-Setup
- GitHub Issues: [Projektname]/issues

## ⚠️ Wichtige Sicherheitswarnung

**Diese Datei enthält sensible Informationen (Standard-Credentials)!**

- Schützen Sie diese Datei auf dem Server
- Fügen Sie `SETUP.md` optional zu `.gitignore` hinzu, wenn Sie unterschiedliche Credentials für verschiedene Environments haben
- Dokumentieren Sie Setup-Schritte für Ihr Team separat und sicher

---

**Version:** 1.0
**Letzte Aktualisierung:** 2026-01-18
