# Anleitung: Session-Sichtbarkeit auf einzelne Benutzer beschränken

## Problem
Aktuell können alle Admin-Benutzer alle Sessions sehen. Dies ist ein Datenschutzproblem, wenn mehrere Lehrkräfte das System nutzen.

## Lösung
Die Datenbank und der Code wurden angepasst, sodass:
- Jede Session einem Admin-Benutzer (Lehrkraft) zugeordnet ist
- Lehrkräfte nur ihre eigenen Sessions sehen und verwalten können
- Andere Admins keinen Zugriff auf fremde Sessions haben

## Installation der Änderungen

### Schritt 1: Datenbank aktualisieren

**WICHTIG:** Führe diese Schritte aus, bevor du die neuen Code-Änderungen verwendest!

#### Option A: Via phpMyAdmin (empfohlen für all-inkl.com)

1. Logge dich in dein all-inkl.com KAS ein
2. Öffne phpMyAdmin für die Datenbank `d045e8fc`
3. Klicke auf "SQL" im oberen Menü
4. Kopiere den Inhalt der Datei `migration_add_session_ownership.sql` und füge ihn ein
5. Klicke auf "OK" um das Script auszuführen

#### Option B: Via MySQL Command Line

```bash
mysql -h localhost -u d045e8fc -p d045e8fc < migration_add_session_ownership.sql
```

### Schritt 2: Überprüfung

Nach der Migration sollte:
- Die Tabelle `sessions` eine neue Spalte `created_by_admin_id` haben
- Alle existierenden Sessions dem ersten Admin-Benutzer zugeordnet sein
- Neue Sessions automatisch dem erstellenden Admin zugeordnet werden

### Schritt 3: Code deployen

Die folgenden Dateien wurden geändert:
- `admin/create.php` - Speichert jetzt die Admin-ID beim Erstellen
- `admin/dashboard.php` - Zeigt nur eigene Sessions an
- `admin/results.php` - Prüft Besitzrechte vor Anzeige

Stelle sicher, dass diese Dateien auf deinem Server aktualisiert werden.

## Bestehende Sessions

Alle Sessions, die vor der Migration erstellt wurden, werden automatisch dem **ersten Admin-Account** zugeordnet.

Falls mehrere Admin-Accounts existieren und Sessions neu verteilt werden sollen, kannst du das manuell in phpMyAdmin machen:

```sql
-- Beispiel: Session mit ID 5 dem Admin mit ID 2 zuweisen
UPDATE sessions SET created_by_admin_id = 2 WHERE id = 5;
```

## Neue Lehrkräfte hinzufügen

Um eine neue Lehrkraft hinzuzufügen, musst du einen neuen Admin-Account in der Tabelle `admin_users` erstellen:

```sql
-- Beispiel: Neue Lehrkraft hinzufügen
INSERT INTO admin_users (username, password_hash)
VALUES ('lehrerin.mueller', '$2y$10$...');  -- Passwort muss mit password_hash() in PHP erstellt werden
```

**Tipp:** Du kannst den Login-Code anpassen, um eine "Registrieren"-Funktion hinzuzufügen.

## Test

1. Logge dich als Admin-Benutzer 1 ein → Erstelle eine Session
2. Logge dich als Admin-Benutzer 2 ein → Du solltest die Session von Admin 1 NICHT sehen
3. Erstelle als Admin 2 eine eigene Session → Diese sollte nur für Admin 2 sichtbar sein

## Sicherheitshinweise

✅ **Was jetzt geschützt ist:**
- Jeder Admin sieht nur seine eigenen Sessions
- Admins können nur ihre eigenen Sessions löschen/bearbeiten
- Ergebnisse sind nur für den Session-Besitzer sichtbar

❌ **Was NICHT geschützt ist:**
- Wenn jemand den 4-stelligen Session-Code kennt, kann er als Teilnehmer Feedback abgeben (das ist gewollt)
- Der Standard-Admin-Account (Username: admin, Passwort: admin123) sollte geändert oder gelöscht werden
