# Feedbackspinne

**Kollaboratives Reflexions- und Feedback-Tool mit Live-Visualisierung**

Feedbackspinne ist ein interaktives Netzdiagramm-Tool (Radar-Chart) für Workshop-Feedback und kollaborative Reflexionen. Ideal für Workshops, Seminare und Schulungen, bei denen Teilnehmende ihre Einschätzungen zu verschiedenen Dimensionen abgeben und die Ergebnisse live visualisiert werden.

---

## Features

- **Einfache Session-Verwaltung**: Admins erstellen Sessions mit einem 4-stelligen Code
- **Live-Feedback**: Teilnehmende geben ihre Bewertungen über Slider ein
- **Netzdiagramm-Visualisierung**: Durchschnittswerte werden als interaktives Radar-Chart dargestellt
- **Multi-dimensionale Bewertung**: Beliebig viele Bewertungsdimensionen pro Session
- **Anonyme oder namentliche Teilnahme**: Teilnehmende können optional ihren Namen angeben
- **Export-Funktionen**: Ergebnisse als PNG oder PDF exportieren
- **Multi-User-Support**: Mehrere Admins können parallel arbeiten, jeder sieht nur seine eigenen Sessions
- **Responsive Design**: Funktioniert auf Desktop, Tablet und Smartphones
- **Datenschutz**: Daten bleiben auf eigenem Server, keine externen Services

---

## Technologie-Stack

**Backend:**
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.2+
- PDO für sichere Datenbankzugriffe

**Frontend:**
- Vanilla JavaScript (keine Frameworks)
- HTML5 & CSS3
- Chart.js für Radar-Diagramme
- html2canvas & jsPDF für Export-Funktionen

**Sicherheit:**
- Bcrypt Password Hashing
- CSRF-Token-Validierung
- SQL Injection Prevention (Prepared Statements)
- XSS Protection
- Rate Limiting
- Session Management

---

## Anwendungsbeispiele

- **Workshop-Feedback**: Teilnehmende bewerten verschiedene Aspekte eines Workshops
- **Selbstreflexion**: Team-Mitglieder schätzen ihre Kompetenzen in verschiedenen Bereichen ein
- **Evaluationen**: Schnelles Feedback zu Veranstaltungen oder Schulungen
- **Gruppenarbeit**: Kollaborative Bewertung von Projekten oder Konzepten
- **Kita/Bildung**: Reflexion pädagogischer Konzepte (z.B. KI-Einsatz in der Kita-Verwaltung)

---

## Installation

### Voraussetzungen

- PHP 8.0 oder höher
- MySQL 5.7+ oder MariaDB 10.2+
- Webserver (Apache oder Nginx)
- Composer (optional, keine externen Dependencies erforderlich)

### Schritt 1: Repository klonen

```bash
git clone https://github.com/71Professor/feedbackspinne.git
cd feedbackspinne
```

### Schritt 2: Umgebungsvariablen konfigurieren

Kopiere die `.env.example` Datei zu `.env`:

```bash
cp .env.example .env
```

Bearbeite die `.env` Datei und trage deine Datenbank-Zugangsdaten ein:

```env
DB_HOST=localhost
DB_NAME=feedbackspinne
DB_USER=dein_benutzer
DB_PASS=dein_sicheres_passwort

# Generiere einen zufälligen String (z.B.: openssl rand -base64 32)
SECURE_KEY=generiere_einen_zufaelligen_string_hier

# Debug-Modus (nur für Entwicklung)
DEBUG_MODE=false
```

**Wichtig:** Ändere unbedingt den `SECURE_KEY` zu einem zufälligen String!

```bash
# Zufälligen Key generieren:
openssl rand -base64 32
```

### Schritt 3: Datenbank einrichten

Erstelle eine neue MySQL/MariaDB Datenbank:

```sql
CREATE DATABASE feedbackspinne CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Führe die Datenbank-Migrationen aus:

```bash
mysql -u dein_benutzer -p feedbackspinne < migration_add_session_ownership.sql
mysql -u dein_benutzer -p feedbackspinne < migration_add_chart_color.sql
```

Die Tabellen `sessions`, `submissions` und `admin_users` werden automatisch erstellt.

### Schritt 4: Admin-Account erstellen

Der Standard-Admin-Account wird bei der ersten Migration angelegt:

```
Benutzername: admin
Passwort: admin123
```

**WICHTIG:** Dieses Passwort **muss** nach dem ersten Login geändert werden!

#### Passwort ändern

1. Generiere einen neuen Passwort-Hash:

```bash
# Mit dem mitgelieferten Tool:
php generate-hash.php
```

Oder manuell:

```php
<?php
echo password_hash('DeinNeuesSicheresPasswort', PASSWORD_DEFAULT);
?>
```

2. Aktualisiere den Hash in der Datenbank:

```sql
UPDATE admin_users
SET password_hash = '$2y$10$DEIN_GENERIERTER_HASH'
WHERE username = 'admin';
```

3. **Lösche das `generate-hash.php` Skript nach Verwendung!**

### Schritt 5: Webserver konfigurieren

#### Apache

Erstelle eine `.htaccess` Datei im Root-Verzeichnis:

```apache
# .env Datei schützen
<Files ".env">
    Require all denied
</Files>

# HTTPS erzwingen (empfohlen)
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

#### Nginx

```nginx
location ~ /\.env {
    deny all;
    return 404;
}

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### Schritt 6: Dateiberechtigungen setzen

```bash
# Dateien: 644, Ordner: 755
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# .env nur für Webserver lesbar
chmod 600 .env
```

### Schritt 7: Installation testen

Öffne im Browser:

```
https://deine-domain.de/feedbackspinne/admin/
```

Logge dich mit dem Admin-Account ein und erstelle eine Test-Session.

---

## Verwendung

### Für Admins: Session erstellen

1. Logge dich im Admin-Bereich ein: `/admin/`
2. Klicke auf **"+ Neue Session"**
3. Fülle das Formular aus:
   - **Titel**: Beschreibender Name für deine Session
   - **Beschreibung**: Optional, erklärt den Zweck
   - **Skala**: Min/Max-Werte (z.B. 1-10)
   - **Dimensionen**: Mindestens 3 Bewertungsdimensionen (z.B. "Verständlichkeit", "Praxisbezug", "Relevanz")
   - **Chart-Farbe**: Farbe des Netzdiagramms (optional)
4. Klicke auf **"Session erstellen"**
5. Notiere den **4-stelligen Code** (z.B. `3847`)

### Für Teilnehmende: An Session teilnehmen

1. Öffne die Startseite: `https://deine-domain.de/feedbackspinne/`
2. Gib den 4-stelligen Code ein (vom Admin erhalten)
3. Bewerte die Dimensionen mit den Slidern
4. Optional: Gib deinen Namen ein
5. Klicke auf **"Werte absenden"**

**Direktlink für Teilnehmende:**
```
https://deine-domain.de/feedbackspinne/session.php?code=3847
```

### Ergebnisse ansehen und exportieren

1. Im Admin-Dashboard auf **"Ergebnisse"** bei der gewünschten Session klicken
2. Das Netzdiagramm zeigt die Durchschnittswerte aller Teilnehmenden
3. Liste aller Submissions mit individuellen Werten
4. Export-Optionen:
   - **PNG**: Diagramm als Bild exportieren
   - **PDF**: Diagramm als PDF-Dokument exportieren

---

## Sicherheitshinweise

- **Admin-Passwort ändern**: Das Standard-Passwort `admin123` **muss** sofort geändert werden!
- **SECURE_KEY ändern**: Ändere den Standard-Wert in der `.env` Datei zu einem zufälligen String
- **HTTPS verwenden**: Aktiviere SSL/TLS für verschlüsselte Verbindungen
- **DEBUG_MODE deaktivieren**: Setze `DEBUG_MODE=false` in Produktion
- **.env schützen**: Stelle sicher, dass die `.env` Datei nicht öffentlich zugänglich ist
- **Regelmäßige Backups**: Sichere deine Datenbank regelmäßig
- **Updates**: Halte PHP und MySQL auf dem neuesten Stand
- **Hilfs-Skripte löschen**: Entferne `generate-hash.php` und `seed-example.php` nach der Einrichtung

### Sicherheits-Features

Das Projekt implementiert folgende Sicherheitsmaßnahmen:

- Bcrypt Password Hashing (OWASP-konform)
- CSRF-Token-Validierung auf allen Formularen
- Prepared Statements gegen SQL Injection
- XSS-Schutz durch `htmlspecialchars()`
- Rate Limiting beim Login (5 Versuche / 15 Min)
- Rate Limiting für Session-Codes (10 Versuche / 15 Min)
- Session Timeout (1 Stunde)
- Session Regeneration gegen Session Fixation
- IP-basierte Brute-Force-Prevention

---

## Datenbank-Struktur

Das System verwendet drei Haupt-Tabellen:

### `sessions`
Speichert Workshop-Sessions mit:
- 4-stelliger einzigartiger Code
- Titel, Beschreibung, Skala
- JSON-gespeicherte Dimensionen
- Chart-Farbe
- Ownership (created_by_admin_id)

### `submissions`
Speichert Teilnehmer-Feedback mit:
- Verknüpfung zur Session
- JSON-gespeicherte Bewertungen
- Optionaler Teilnehmername
- Zeitstempel

### `admin_users`
Speichert Admin-Accounts mit:
- Benutzername (unique)
- Bcrypt-gehashtes Passwort
- Zeitstempel

---

## Workflow-Beispiel: Workshop

**Vorbereitung:**
1. Admin erstellt Session "KI-Workshop Reflexion" mit Code `7429`
2. Admin bereitet QR-Code mit Link vor: `https://deine-domain.de/feedbackspinne/session.php?code=7429`

**Während des Workshops:**
3. Admin zeigt Ergebnis-Seite per Beamer
4. Teilnehmende scannen QR-Code oder geben Code manuell ein
5. Teilnehmende bewerten die Dimensionen
6. Admin aktualisiert Ergebnis-Seite (F5) für Live-Updates
7. Durchschnittswerte werden in Echtzeit im Netzdiagramm angezeigt

**Nach dem Workshop:**
8. Admin exportiert Ergebnisse als PNG/PDF
9. Admin kann Session deaktivieren oder löschen

---

## Tipps & Tricks

### QR-Codes für einfachen Zugang
Generiere QR-Codes für deine Session-Links auf [qr-code-generator.com](https://www.qr-code-generator.com) und zeige sie per Beamer oder drucke sie aus.

### Mehrere Sessions parallel
Das System unterstützt beliebig viele parallele Sessions. Jede Session hat ihren eigenen 4-stelligen Code.

### Live-Updates
Die Ergebnis-Seite aktualisiert sich nicht automatisch. Drücke F5 oder aktualisiere manuell, um neue Submissions zu sehen.

### Sessions verwalten
- **Deaktivieren**: Session-Code funktioniert nicht mehr, Daten bleiben erhalten
- **Löschen**: Alle Daten werden permanent gelöscht
- **Kopieren**: Erstelle eine Kopie einer bestehenden Session mit neuen Dimensionen
- **Bearbeiten**: Titel, Beschreibung und Dimensionen können nachträglich geändert werden

---

## Troubleshooting

### Datenbankverbindung fehlgeschlagen
- Überprüfe die Zugangsdaten in der `.env` Datei
- Stelle sicher, dass die Datenbank existiert
- Prüfe, ob MySQL/MariaDB läuft

### Login funktioniert nicht
- Versuche Standard-Credentials: `admin` / `admin123`
- Lösche Browser-Cookies und Cache
- Setze Passwort in der Datenbank zurück (siehe oben)

### Weiße Seite / Fehler
- Aktiviere `DEBUG_MODE=true` in der `.env`
- Prüfe PHP Error Logs
- Stelle sicher, dass PHP 8.0+ installiert ist

### Session nicht gefunden
- Überprüfe, ob der Code korrekt ist (4 Ziffern)
- Prüfe im Admin-Dashboard, ob die Session aktiv ist

---

## Backup & Wartung

### Datenbank-Backup erstellen

**Über CLI:**
```bash
mysqldump -u dein_benutzer -p feedbackspinne > backup_$(date +%Y%m%d).sql
```

**Über phpMyAdmin:**
1. Datenbank auswählen
2. "Exportieren" klicken
3. "Schnell" oder "Benutzerdefiniert" wählen
4. "OK" klicken und .sql-Datei speichern

### Empfohlene Backup-Routine
- Vor jedem größeren Workshop
- Wöchentlich bei regelmäßiger Nutzung
- Vor Updates oder Migrationen

---

## Lizenz

CC-BY-SA 4.0 Feedbackspinne von Michael Kohl

---

## Support & Kontakt

Bei Fragen oder Problemen:
hallo@digitales-und-bildung.de
---

## Credits

Entwickelt für Workshop-Feedback und kollaborative Reflexion in Bildungskontexten.

**Technologien:**
- [Chart.js](https://www.chartjs.org/) - Netzdiagramm-Visualisierung
- [html2canvas](https://html2canvas.hertzen.com/) - Screenshot-Export
- [jsPDF](https://github.com/parallax/jsPDF) - PDF-Export

---

**Version:** 1.0 (Januar 2026)

Viel Erfolg mit Feedbackspinne!
