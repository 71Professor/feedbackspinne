# Kollaboratives Netzdiagramm-Tool - Installationsanleitung

## 📋 Übersicht

Dieses System ermöglicht es dir, Workshop-Sessions zu erstellen, bei denen Teilnehmende ihre Werte eingeben und du als Admin die aggregierten Durchschnittswerte aller Teilnehmenden siehst.

### Features
✅ Admin erstellt Sessions mit 4-stelligem Code  
✅ Teilnehmende geben ihre Werte ein  
✅ System berechnet automatisch Durchschnittswerte  
✅ Live-Dashboard für Admin mit Export-Funktion  
✅ Beliebig viele parallele Sessions möglich  
✅ Responsive Design für Desktop, Tablet und Mobile  

---

## 🚀 Installation auf all-inkl.com

### Schritt 1: MySQL-Datenbank einrichten

1. **Logge dich im KAS ein** (https://kas.all-inkl.com)
2. **Gehe zu "Datenbank"**
3. **Erstelle eine neue MySQL-Datenbank:**
   - Klicke auf "Neue Datenbank anlegen"
   - Wähle einen Namen (z.B. `netzdiagramm`)
   - Notiere dir:
     - Datenbank-Name
     - Benutzername
     - Passwort
     - Host (meist `localhost`)

4. **Führe das SQL-Setup aus:**
   - Öffne "phpMyAdmin" im KAS
   - Wähle deine neue Datenbank aus
   - Klicke auf "SQL"
   - Kopiere den Inhalt von `setup.sql` rein
   - Klicke auf "OK"

### Schritt 2: Dateien hochladen

1. **Verbinde dich per FTP** oder nutze den **KAS-Dateimanager**
2. **Navigiere zu deinem Webspace** (meist `/www/`)
3. **Erstelle einen Ordner** (z.B. `/reflexion/` oder `/netzdiagramm/`)
4. **Lade alle Dateien hoch:**
   ```
   /reflexion/
   ├── config.php
   ├── index.php
   ├── session.php
   ├── admin/
   │   ├── index.php
   │   ├── dashboard.php
   │   ├── create.php
   │   ├── results.php
   │   └── logout.php
   ```

### Schritt 3: Konfiguration anpassen

#### 3.1 config.php Datei finden und öffnen

Die Datei `config.php` ist die zentrale Konfigurationsdatei. Sie befindet sich im Hauptverzeichnis deiner Installation.

**Wo finde ich die Datei?**
- **Lokal auf deinem Computer:** Im entpackten ZIP-Ordner: `netzdiagramm-kollaborativ/config.php`
- **Auf dem Server:** Nach dem Upload unter `/reflexion/config.php` (bzw. deinem gewählten Ordnernamen)

**Womit öffnen?**

✅ **Empfohlene Editoren (kostenlos):**
- **Windows:** Notepad++, Visual Studio Code, Sublime Text
- **Mac:** TextEdit (im Plain-Text-Modus!), Visual Studio Code, Sublime Text
- **Online:** Direkt im Browser über KAS-Dateimanager → Datei markieren → "Bearbeiten"

❌ **NICHT verwenden:**
- Microsoft Word (fügt Formatierungen hinzu)
- Standard-Notepad (kann Probleme mit Zeilenumbrüchen machen)

#### 3.2 Datenbank-Zugangsdaten eintragen

Suche in der `config.php` nach diesem Bereich (ca. Zeile 8-11):

```php
// Datenbank-Verbindungseinstellungen
define('DB_HOST', 'localhost');
define('DB_NAME', 'deine_datenbank');
define('DB_USER', 'dein_user');
define('DB_PASS', 'dein_passwort');
```

**Was musst du ändern?**

Ersetze die Beispielwerte mit deinen echten Daten aus Schritt 1:

```php
// VORHER (Standard):
define('DB_NAME', 'deine_datenbank');

// NACHHER (Beispiel):
define('DB_NAME', 'db123456_netzdiagramm');
```

**Konkrete Beispiele:**

**Beispiel 1 - Typische all-inkl.com Daten:**
```php
define('DB_HOST', 'localhost');                  // bleibt meistens so
define('DB_NAME', 'db123456_reflexion');         // deine DB aus KAS
define('DB_USER', 'db123456');                   // dein DB-User aus KAS
define('DB_PASS', 'meinGeheimesPW2024!');       // dein DB-Passwort
```

**Beispiel 2 - Mit Subdomain-Hosting:**
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'workshop_db');
define('DB_USER', 'workshop_user');
define('DB_PASS', 'Xk9#mP2qL7zR');
```

**📝 WICHTIG - Häufige Fehler vermeiden:**

❌ **Falsch:**
```php
define('DB_NAME', deine_datenbank);      // Keine Anführungszeichen
define('DB_NAME', 'deine_datenbank);     // Fehlendes Anführungszeichen
define('DB_NAME', "deine_datenbank');    // Gemischte Anführungszeichen
define('DB_PASS', 'Passwort mit 'nem Apostroph');  // Unescapetes Apostroph
```

✅ **Richtig:**
```php
define('DB_NAME', 'deine_datenbank');    // Korrekt in Anführungszeichen
define('DB_PASS', 'Passwort mit nem Apostroph'); // Apostroph vermieden
// oder:
define('DB_PASS', 'Passwort mit \'nem Apostroph'); // Apostroph escaped
```

**Wo finde ich meine Zugangsdaten?**

1. **Im KAS:** "Datenbank" → Deine Datenbank auswählen → Zugangsdaten werden angezeigt
2. **In der Willkommens-E-Mail** von all-inkl (falls vorhanden)
3. **Neu generieren:** Im KAS → "Datenbank" → Passwort zurücksetzen

#### 3.3 SECURE_KEY ändern (WICHTIG!)

Suche nach dieser Zeile (ca. Zeile 17):

```php
define('SECURE_KEY', 'dein-geheimer-schluessel-aendern');
```

**Was ist der SECURE_KEY?**
- Ein geheimer Schlüssel für zusätzliche Sicherheit
- Wird für Session-Verschlüsselung verwendet
- **MUSS geändert werden** - niemals den Standard-Wert nutzen!

**Wie generiere ich einen sicheren Key?**

**Option 1: Online-Generator (schnell & einfach)**
1. Gehe zu https://randomkeygen.com/
2. Kopiere einen Schlüssel aus "Fort Knox Passwords" (ganz unten)
3. Füge ihn ein:

```php
define('SECURE_KEY', 'Xk9mP2qL7zR4wNpY6bTcV8hSdG3fJaK5');
```

**Option 2: Eigener zufälliger String**
- Mindestens 32 Zeichen
- Mix aus Groß-/Kleinbuchstaben, Zahlen, Sonderzeichen
- Keine sinnvollen Wörter

```php
// Beispiele für GUTE Keys:
define('SECURE_KEY', 'a9Km#Lp2$Nq4!Rw6xYz8vBc0mHj3fDg5sAt7uKe1iOw9');
define('SECURE_KEY', 'Z7y!X5w@V3u#T1r$Q9p^M8n&L6k%J4h*G2f(D0b)A8c');
define('SECURE_KEY', 'workshop-2026-mike-kita-bayern-XkL93#mZp2qR7');
```

**❌ Schlechte Keys (NICHT verwenden!):**
```php
define('SECURE_KEY', 'passwort123');              // Zu einfach
define('SECURE_KEY', 'dein-geheimer-schluessel'); // Standard-Text
define('SECURE_KEY', '12345678');                 // Nur Zahlen
```

#### 3.4 Debug-Modus verstehen (Optional)

Weiter unten in der config.php findest du:

```php
define('DEBUG_MODE', false);
```

**Was bedeutet das?**
- `false` = Produktiv-Modus (keine Fehlermeldungen sichtbar) ← **Standard**
- `true` = Debug-Modus (detaillierte Fehlermeldungen) ← Nur bei Problemen

**Wann auf `true` setzen?**
- Bei Installation, um Fehler zu finden
- Bei Problemen mit Datenbankverbindung
- Bei weißen Seiten/Fehlern

**⚠️ WICHTIG:** Nach Behebung des Problems wieder auf `false` setzen!

```php
// Während Installation/Fehlersuche:
define('DEBUG_MODE', true);   // Zeigt alle Fehler

// Nach erfolgreicher Installation:
define('DEBUG_MODE', false);  // Versteckt Fehler vor Nutzern
```

#### 3.5 Datei speichern und hochladen

**Wenn du lokal bearbeitet hast:**

1. **Speichern:** Strg+S (Windows) oder Cmd+S (Mac)
2. **Per FTP hochladen:**
   - Öffne dein FTP-Programm (FileZilla, WinSCP, etc.)
   - Verbinde dich mit deinem Server
   - Navigiere zu `/www/reflexion/`
   - Ziehe `config.php` ins Fenster
   - Überschreiben bestätigen

**Wenn du direkt im KAS-Dateimanager bearbeitet hast:**
1. Klicke auf "Speichern"
2. Fertig!

#### 3.6 Konfiguration testen

**Schnelltest - Ist die Konfiguration korrekt?**

1. Öffne im Browser: `https://deine-domain.de/reflexion/`
2. **Wenn die Startseite erscheint:** ✅ Konfiguration funktioniert!
3. **Wenn Fehlermeldung erscheint:** ❌ Siehe unten

**Häufige Fehler und Lösungen:**

| Fehlermeldung | Ursache | Lösung |
|---------------|---------|--------|
| "SQLSTATE[HY000] [1045] Access denied" | Falscher DB_USER oder DB_PASS | Zugangsdaten in KAS überprüfen |
| "SQLSTATE[HY000] [2002] Connection refused" | Falscher DB_HOST | Meist 'localhost', manchmal IP-Adresse |
| "Unknown database 'XXX'" | Datenbank existiert nicht | DB-Name prüfen oder Datenbank neu erstellen |
| "Parse error in config.php" | Syntax-Fehler in PHP | Anführungszeichen, Semikolons prüfen |
| Weiße Seite, keine Meldung | PHP-Fehler ohne Debug-Mode | DEBUG_MODE auf true setzen |

**Detaillierter Test:**

Erstelle temporär eine Test-Datei `test-db.php` im gleichen Ordner:

```php
<?php
require_once 'config.php';

try {
    $pdo = getDB();
    echo "✅ Datenbankverbindung erfolgreich!<br>";
    echo "Verbunden mit: " . DB_NAME . "<br>";
    
    // Teste ob Tabellen existieren
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Gefundene Tabellen: " . count($tables) . "<br>";
    foreach ($tables as $table) {
        echo "- " . $table . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage();
}
?>
```

Rufe auf: `https://deine-domain.de/reflexion/test-db.php`

**Erwartete Ausgabe bei Erfolg:**
```
✅ Datenbankverbindung erfolgreich!
Verbunden mit: db123456_reflexion
Gefundene Tabellen: 3
- sessions
- submissions
- admin_users
```

**⚠️ WICHTIG:** Lösche `test-db.php` nach dem Test wieder!

### Schritt 4: Erste Anmeldung

#### 4.1 Admin-Login-Seite aufrufen

**URL zusammenstellen:**

Deine Admin-URL setzt sich zusammen aus:
```
https://deine-domain.de/[dein-ordner]/admin/
```

**Konkrete Beispiele:**

| Deine Domain | Upload-Ordner | Admin-URL |
|-------------|---------------|-----------|
| www.workshop-tools.de | `/reflexion/` | https://www.workshop-tools.de/reflexion/admin/ |
| kita-bayern.de | `/netzdiagramm/` | https://kita-bayern.de/netzdiagramm/admin/ |
| mike-mueller.de | `/tools/` | https://mike-mueller.de/tools/admin/ |

**Tipp:** Speichere die URL als Lesezeichen in deinem Browser!

#### 4.2 Standard-Login verwenden

Wenn die Login-Seite geladen hat, siehst du ein Formular mit zwei Feldern:

**📝 Standard-Zugangsdaten:**
```
Benutzername: admin
Passwort:     admin123
```

**So loggst du dich ein:**

1. Gib im Feld "Benutzername" ein: `admin`
2. Gib im Feld "Passwort" ein: `admin123`
3. Klicke auf "Anmelden"

**Was du sehen solltest:**
- ✅ **Erfolg:** Du wirst zum Dashboard weitergeleitet (dashboard.php)
- ❌ **Fehler:** "Ungültige Anmeldedaten" → Siehe Troubleshooting unten

#### 4.3 Erste Orientierung im Dashboard

Nach erfolgreichem Login landest du auf dem **Admin-Dashboard**.

**Was du siehst:**
```
┌────────────────────────────────────────────────┐
│  📊 Session-Verwaltung                         │
│                                   [+ Neue Sess]│
├────────────────────────────────────────────────┤
│                                                │
│  Noch keine Sessions                           │
│  Erstelle deine erste Session, um zu beginnen.│
│                                                │
│           [Jetzt Session erstellen]            │
│                                                │
└────────────────────────────────────────────────┘
```

**Wichtige Elemente:**
- **"+ Neue Session"** (oben rechts) → Neue Workshop-Session erstellen
- **"Abmelden"** (oben rechts) → Logout
- Session-Karten werden hier angezeigt (sobald vorhanden)

#### 4.4 Passwort SOFORT ändern (KRITISCH!)

⚠️ **EXTREM WICHTIG:** Das Standard-Passwort `admin123` ist **JEDEM** bekannt!

**Warum ist das gefährlich?**
- Jeder könnte sich einloggen
- Jeder könnte deine Sessions löschen
- Jeder könnte Teilnehmer-Daten sehen
- Jeder könnte neue Admin-Accounts erstellen

**Du hast 2 Optionen:**

---

**OPTION 1: Über phpMyAdmin (Empfohlen - am sichersten)**

**Schritt 1: Neues Passwort generieren**

Erstelle temporär eine Datei `generate-hash.php` im Hauptordner:

```php
<?php
// Dein gewünschtes neues Passwort hier eintragen:
$neues_passwort = 'MeinSicheresPasswort2026!';

// Hash generieren:
$hash = password_hash($neues_passwort, PASSWORD_DEFAULT);

echo "Dein neues Passwort: " . $neues_passwort . "<br>";
echo "Der Hash (kopiere das): <br>";
echo "<strong>" . $hash . "</strong>";
?>
```

**Schritt 2: Hash generieren**

1. Lade `generate-hash.php` in `/reflexion/` hoch
2. Rufe auf: `https://deine-domain.de/reflexion/generate-hash.php`
3. Du siehst:
   ```
   Dein neues Passwort: MeinSicheresPasswort2026!
   Der Hash (kopiere das):
   $2y$10$abcdefghijk...xyz123
   ```
4. **Kopiere den gesamten Hash** (die lange Zeichenkette mit `$2y$10$...`)

**Schritt 3: Hash in Datenbank eintragen**

1. **Öffne phpMyAdmin** im KAS
2. **Wähle deine Datenbank** (links in der Liste)
3. **Klicke auf die Tabelle `admin_users`**
4. **Klicke auf "Bearbeiten"** (Stift-Symbol) bei der Zeile mit `admin`
5. **Finde das Feld `password_hash`**
6. **Markiere den KOMPLETTEN alten Wert** und lösche ihn
7. **Füge deinen neuen Hash ein** (mit Strg+V)
8. **Klicke auf "OK"** (unten rechts)

**Visuell:**
```
Tabelle: admin_users
┌────┬──────────┬─────────────────────────────────────┐
│ id │ username │ password_hash                       │
├────┼──────────┼─────────────────────────────────────┤
│ 1  │ admin    │ $2y$10$92IXU... ← ALTER Hash        │
│    │          │                                     │
│    │          │ [Bearbeiten] [Löschen]              │
└────┴──────────┴─────────────────────────────────────┘

Nach dem Bearbeiten:
┌────┬──────────┬─────────────────────────────────────┐
│ id │ username │ password_hash                       │
├────┼──────────┼─────────────────────────────────────┤
│ 1  │ admin    │ $2y$10$abcdef... ← NEUER Hash      │
└────┴──────────┴─────────────────────────────────────┘
```

**Schritt 4: Aufräumen**

1. **Lösche die Datei `generate-hash.php` SOFORT!**
   - Per FTP löschen ODER
   - Im KAS-Dateimanager löschen
2. **Teste neues Login:**
   - Logout (oben rechts "Abmelden")
   - Login mit: `admin` / `MeinSicheresPasswort2026!`
   - ✅ Funktioniert? Super!
   - ❌ Funktioniert nicht? Hash nochmal prüfen

---

**OPTION 2: Zweiten Admin-Account erstellen (Alternative)**

Wenn du den ersten Account nicht ändern möchtest, erstelle einen eigenen:

**Schritt 1: Hash generieren** (wie oben in Option 1, Schritt 1+2)

**Schritt 2: Neuen User in Datenbank eintragen**

1. Öffne phpMyAdmin → deine Datenbank → Tabelle `admin_users`
2. Klicke oben auf **"Einfügen"** (nicht "Bearbeiten"!)
3. Fülle die Felder aus:
   ```
   id:            (leer lassen - wird automatisch vergeben)
   username:      mike          ← dein Wunsch-Benutzername
   password_hash: $2y$10$abc... ← dein generierter Hash
   created_at:    (leer lassen)
   ```
4. Klicke auf "OK"

**Schritt 3: Mit neuem Account einloggen**

- Benutzername: `mike` (oder wie du gewählt hast)
- Passwort: `MeinSicheresPasswort2026!` (wie bei Hash-Generierung)

**Optional:** Alten `admin`-Account löschen:
- In phpMyAdmin → `admin_users` → Bei Zeile mit `admin` auf "Löschen" klicken

---

#### 4.5 Erste Test-Session erstellen

Jetzt da du sicher eingeloggt bist, teste das System:

**Schritt 1: Session erstellen**

1. Klicke auf **"+ Neue Session"** (oben rechts)
2. Fülle das Formular aus:
   ```
   Session-Titel:  Test-Session
   Beschreibung:   Dies ist ein Test
   Skala Min:      1
   Skala Max:      5
   ```
3. Die 3 Standard-Dimensionen sind schon da - belasse sie oder ändere sie
4. Klicke auf **"Session erstellen"**

**Schritt 2: Code notieren**

Du siehst eine Erfolgsmeldung mit einem **4-stelligen Code**, z.B.:
```
✅ Session erfolgreich erstellt!
Deine Session wurde erstellt. Teile diesen Code:

    3847
```

**Notiere diesen Code!**

**Schritt 3: Als Teilnehmer testen**

1. Öffne ein **neues Browser-Fenster** (Inkognito/Privat-Modus)
2. Gehe zu: `https://deine-domain.de/reflexion/`
3. Gib den Code ein: `3847`
4. Fülle die Regler aus
5. Klicke auf "Werte absenden"
6. ✅ "Vielen Dank!" erscheint? **Perfekt!**

**Schritt 4: Ergebnisse ansehen**

1. Zurück zum Admin-Fenster
2. Klicke auf **"📈 Ergebnisse"** bei deiner Test-Session
3. Du solltest sehen:
   - Netzdiagramm mit deinen eingegebenen Werten
   - "Bisherige Teilnehmer: 1"
   - Deine Submission in der Liste

**🎉 Wenn das alles funktioniert: Installation erfolgreich!**

#### 4.6 Troubleshooting - Login-Probleme

**Problem 1: "Ungültige Anmeldedaten"**

**Mögliche Ursachen:**
- Tippfehler bei Benutzername oder Passwort
- Caps Lock aktiviert
- Leerzeichen vor/nach dem Passwort
- Browser speichert altes Passwort (nach Passwort-Änderung)

**Lösung:**
1. Prüfe Groß-/Kleinschreibung
2. Kopiere Passwort aus deiner Notiz (kein Tippen)
3. Lösche Browser-Cache und gespeicherte Passwörter:
   - Chrome: Strg+Shift+Delete
   - Firefox: Strg+Shift+Delete
   - Cookies für deine Domain löschen

**Problem 2: Weiße Seite beim Login**

**Ursache:** PHP-Fehler

**Lösung:**
1. Setze in `config.php`: `define('DEBUG_MODE', true);`
2. Lade Seite neu
3. Lies Fehlermeldung
4. Häufig: Datenbankverbindung fehlerhaft → Zurück zu Schritt 3

**Problem 3: "Session konnte nicht gestartet werden"**

**Ursache:** PHP-Session-Verzeichnis nicht beschreibbar

**Lösung:**
1. Kontaktiere all-inkl Support ODER
2. Füge in `config.php` nach `session_start();` hinzu:
   ```php
   ini_set('session.save_path', '/pfad/zu/tmp');
   ```

**Problem 4: Nach Login sofort wieder auf Login-Seite**

**Ursache:** Cookies werden nicht gespeichert

**Lösung:**
1. **Prüfe Browser-Einstellungen:**
   - Cookies müssen erlaubt sein
   - Keine Tracking-Blocker für deine Domain
2. **Prüfe HTTPS:**
   - Nutzt du HTTPS? `https://` statt `http://`?
   - SSL-Zertifikat aktiv in all-inkl KAS?
3. **Domain-Konfiguration:**
   - Rufst du die Seite mit `www.` oder ohne auf?
   - Bleibe bei einer Variante (Weiterleitung einrichten)

**Problem 5: "CSRF-Token ungültig"**

**Ursache:** Session-Problem oder abgelaufene Seite

**Lösung:**
1. Lade Login-Seite neu (F5)
2. Warte 5 Sekunden
3. Versuche erneut einzuloggen
4. Falls weiter Problem: Browser-Cache löschen

**Problem 6: Kann mich nicht mehr einloggen nach Passwort-Änderung**

**Ursache:** Hash falsch eingegeben oder Passwort vergessen

**Lösung - Passwort zurücksetzen:**

1. **Öffne phpMyAdmin**
2. **SQL-Tab** oben
3. **Führe dieses SQL aus:**
   ```sql
   UPDATE admin_users 
   SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
   WHERE username = 'admin';
   ```
4. **Klicke "OK"**
5. **Login mit:** `admin` / `admin123`
6. **SOFORT neues Passwort setzen** (siehe oben)

---

#### 4.7 Best Practices nach der Installation

✅ **Sicherheits-Checkliste:**
- [ ] Passwort geändert
- [ ] SECURE_KEY geändert
- [ ] DEBUG_MODE auf false
- [ ] generate-hash.php gelöscht (falls erstellt)
- [ ] test-db.php gelöscht (falls erstellt)
- [ ] Admin-URL als Lesezeichen gespeichert
- [ ] Test-Session erstellt und getestet

✅ **Backup erstellen:**
1. phpMyAdmin → Datenbank auswählen → "Exportieren"
2. "Schnell" auswählen → "OK"
3. .sql-Datei sicher speichern

✅ **SSL-Zertifikat aktivieren (falls noch nicht):**
1. KAS → Domain → SSL → "Let's Encrypt" aktivieren
2. HTTPS erzwingen in .htaccess:
   ```apache
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

---

## 🔐 Passwort ändern

Das Standard-Passwort **MUSS** geändert werden!

### Option 1: Über phpMyAdmin (empfohlen)

1. Öffne phpMyAdmin im KAS
2. Wähle deine Datenbank
3. Öffne die Tabelle `admin_users`
4. Klicke auf "Bearbeiten" bei dem Admin-User
5. Ersetze den Wert im Feld `password_hash` mit einem neuen Hash:

**So generierst du einen neuen Hash:**
```php
<?php
echo password_hash('dein-neues-passwort', PASSWORD_DEFAULT);
?>
```

- Speichere diesen Code als `generate-hash.php`
- Lade ihn hoch und rufe ihn im Browser auf
- Kopiere den generierten Hash
- Füge ihn in phpMyAdmin ein
- **Lösche die Datei `generate-hash.php` sofort wieder!**

### Option 2: Weiteren Admin-User anlegen

Du kannst auch einen neuen Admin-User direkt in der Datenbank anlegen:

```sql
INSERT INTO admin_users (username, password_hash) 
VALUES ('mike', '$2y$10$DEIN_GENERIERTER_HASH');
```

---

## 📖 Nutzung

### Als Admin: Session erstellen

1. **Logge dich ein:** `https://deine-domain.de/reflexion/admin/`
2. **Klicke auf "Neue Session"**
3. **Fülle das Formular aus:**
   - Titel (z.B. "KI in der Kita-Verwaltung")
   - Beschreibung (optional)
   - Skala (z.B. 1-10)
   - Dimensionen (mindestens 3)
4. **Klicke auf "Session erstellen"**
5. **Notiere den 4-stelligen Code** (z.B. 3847)

### Als Admin: Ergebnisse ansehen

1. **Gehe zum Dashboard**
2. **Klicke bei einer Session auf "📈 Ergebnisse"**
3. Du siehst:
   - Durchschnittliche Bewertung (Netzdiagramm)
   - Einzelwerte pro Dimension
   - Liste aller Teilnehmenden
   - Export-Buttons (PNG/PDF)

### Für Teilnehmende: An Session teilnehmen

1. **Öffne:** `https://deine-domain.de/reflexion/`
2. **Gib den 4-stelligen Code ein** (vom Admin erhalten)
3. **Stelle die Regler ein**
4. **Optional:** Namen eingeben
5. **Klicke auf "Werte absenden"**

**Teilnehmer-Link direkt teilen:**
```
https://deine-domain.de/reflexion/session.php?code=3847
```

---

## 🎯 Workflow-Beispiel: Workshop

**Vorbereitung (1 Tag vorher):**
1. Admin erstellt Session "KI-Reflexion Workshop 13.01.2026"
2. Admin notiert Code: `3847`

**Vor dem Workshop (10 Min vorher):**
3. Admin öffnet Ergebnis-Seite im Browser
4. Admin zeigt diese Seite per Beamer

**Während des Workshop:**
5. Admin teilt Link oder Code mit Teilnehmenden
6. Teilnehmende öffnen Link auf ihren Geräten
7. Teilnehmende füllen ihre Werte aus
8. Admin aktualisiert Ergebnis-Seite (F5)
9. **Live-Visualisierung:** Durchschnittswerte aktualisieren sich

**Nach dem Workshop:**
10. Admin exportiert Ergebnisse als PNG/PDF
11. Admin kann Session deaktivieren oder löschen

---

## 💡 Tipps & Tricks

### Mehrere Sessions parallel

Du kannst beliebig viele Sessions gleichzeitig laufen lassen:
- Verschiedene Workshops
- Verschiedene Gruppen
- Verschiedene Themen

Jede Session hat ihren eigenen 4-stelligen Code.

### Session-Links vorher vorbereiten

Erstelle Links mit QR-Codes für einfacheren Zugang:
1. Erstelle Session
2. Kopiere Link: `https://deine-domain.de/reflexion/session.php?code=XXXX`
3. Generiere QR-Code (z.B. auf https://qr-code-generator.com)
4. Zeige QR-Code per Beamer oder drucke ihn aus

### Live-Updates während Workshop

Die Ergebnis-Seite aktualisiert sich **nicht automatisch**. 
- Drücke F5 oder aktualisiere manuell
- Alternativ: Öffne die Seite in mehreren Browser-Tabs

### Anonyme vs. namentliche Teilnahme

Teilnehmende können **optional** ihren Namen eingeben.
- Ohne Name: "Anonym" in der Liste
- Mit Name: Name wird in der Teilnehmer-Liste angezeigt
- **Wichtig:** Einzelne Werte sind für Admin sichtbar!

### Sessions nach Workshop

**Option 1:** Session deaktivieren
- Code funktioniert nicht mehr
- Daten bleiben erhalten
- Kann später reaktiviert werden

**Option 2:** Session löschen
- Alle Daten werden gelöscht
- Kann nicht rückgängig gemacht werden

---

## 🔧 Problemlösung

### "Datenbankverbindung fehlgeschlagen"

**Ursache:** Falsche Datenbank-Zugangsdaten in `config.php`

**Lösung:**
1. Überprüfe DB_HOST, DB_NAME, DB_USER, DB_PASS
2. Teste Verbindung in phpMyAdmin
3. Stelle sicher, dass die Datenbank existiert

### "Session nicht gefunden"

**Ursache:** Code wurde falsch eingegeben oder Session ist inaktiv

**Lösung:**
1. Überprüfe, ob Code korrekt ist (4 Ziffern)
2. Prüfe im Admin-Dashboard, ob Session aktiv ist

### "Fehler beim Speichern"

**Ursache:** Datenbank-Rechte oder Verbindungsproblem

**Lösung:**
1. Prüfe Datenbank-Berechtigungen
2. Schaue in phpMyAdmin, ob Tabellen existieren
3. Führe `setup.sql` erneut aus, falls Tabellen fehlen

### Admin-Login funktioniert nicht

**Ursache:** Falsches Passwort oder Session-Problem

**Lösung:**
1. Versuche `admin` / `admin123`
2. Lösche Browser-Cookies
3. Setze Passwort in Datenbank zurück (siehe oben)

### Seite lädt, aber bleibt weiß

**Ursache:** PHP-Fehler

**Lösung:**
1. Aktiviere Debug-Mode in `config.php`:
   ```php
   define('DEBUG_MODE', true);
   ```
2. Aktualisiere Seite und schaue nach Fehlermeldungen
3. Deaktiviere Debug-Mode nach Behebung

---

## 📊 Datenbank-Struktur

Das System nutzt 3 Tabellen:

### `sessions`
Speichert Workshop-Sessions
- `id` - Eindeutige ID
- `code` - 4-stelliger Code
- `title` - Session-Titel
- `description` - Beschreibung
- `scale_min` / `scale_max` - Skala
- `dimensions` - JSON mit Dimensionen
- `is_active` - Aktiv/Inaktiv
- `created_at` - Erstellungsdatum

### `submissions`
Speichert Teilnehmer-Eingaben
- `id` - Eindeutige ID
- `session_id` - Verknüpfung zur Session
- `participant_name` - Name (optional)
- `values` - JSON mit Werten
- `submitted_at` - Zeitstempel

### `admin_users`
Speichert Admin-Zugänge
- `id` - Eindeutige ID
- `username` - Benutzername
- `password_hash` - Verschlüsseltes Passwort

---

## 🔒 Sicherheitshinweise

### Wichtige Sicherheitsmaßnahmen:

1. **Ändere das Admin-Passwort sofort!**
2. **Ändere den SECURE_KEY in config.php**
3. **Deaktiviere DEBUG_MODE in Produktion**
4. **Nutze HTTPS** (SSL-Zertifikat bei all-inkl aktivieren)
5. **Regelmäßige Backups** der Datenbank

### Optional: Admin-Bereich zusätzlich schützen

Du kannst den `/admin/` Ordner zusätzlich per `.htaccess` schützen:

1. Erstelle `.htaccess` in `/admin/`:
```apache
AuthType Basic
AuthName "Admin-Bereich"
AuthUserFile /pfad/zu/.htpasswd
Require valid-user
```

2. Erstelle `.htpasswd` mit verschlüsseltem Passwort
3. Stelle sicher, dass der Pfad absolut ist

---

## 📦 Backup & Wartung

### Datenbank-Backup erstellen

1. Öffne phpMyAdmin
2. Wähle deine Datenbank
3. Klicke auf "Exportieren"
4. Wähle "Benutzerdefiniert"
5. Stelle sicher, dass alle Tabellen ausgewählt sind
6. Klicke auf "OK"
7. **Speichere die .sql-Datei sicher!**

### Empfohlene Backup-Routine

- **Vor jedem Workshop:** Backup erstellen
- **Nach Workshop:** Optional Backup erstellen
- **Monatlich:** Komplettes Backup (Datenbank + Dateien)

---

## 🆘 Support & Kontakt

Bei technischen Problemen:

1. **Prüfe die Problemlösung-Sektion** (siehe oben)
2. **Aktiviere Debug-Mode** um Fehlermeldungen zu sehen
3. **Prüfe phpMyAdmin** ob Daten korrekt gespeichert werden

---

## 📝 Changelog

**Version 1.0 (Januar 2026)**
- Initiale Version
- Admin-Dashboard
- Session-Erstellung
- Teilnehmer-Interface
- Ergebnis-Visualisierung
- Export-Funktionen (PNG/PDF)

---

Viel Erfolg mit dem Tool! 🚀
