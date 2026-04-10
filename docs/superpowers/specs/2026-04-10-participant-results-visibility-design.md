# Design: Ergebnis-Sichtbarkeit für Teilnehmende

**Datum:** 2026-04-10  
**Status:** Genehmigt

---

## Zusammenfassung

Session-Ersteller können beim Anlegen (und nachträglich beim Bearbeiten) einer Session festlegen, ob Teilnehmende nach dem Absenden ihrer Stimme das aggregierte Gesamtergebnis aller bisherigen Einreichungen sehen dürfen. Die Anzeige erfolgt direkt in `session.php` unterhalb der Danke-Meldung als Radar-Chart mit Durchschnittswerten.

---

## Betroffene Dateien

| Datei | Änderungsart |
|---|---|
| `migration_add_show_results.sql` | Neu — DB-Spalte hinzufügen |
| `run_migration_show_results.php` | Neu — Migration ausführen |
| `config.php` | Anpassen — `validateSessionData()` |
| `admin/create.php` | Anpassen — Toggle-UI + INSERT |
| `admin/edit.php` | Anpassen — Toggle-UI + UPDATE |
| `session.php` | Anpassen — Ergebnis-Block nach Submit |

---

## 1. Datenbankschema

```sql
ALTER TABLE sessions
    ADD COLUMN show_results_to_participants TINYINT(1) NOT NULL DEFAULT 0;
```

**Migration-Dateien** nach bestehendem Muster:
- `migration_add_show_results.sql` — enthält das ALTER TABLE
- `run_migration_show_results.php` — führt die Migration aus, gibt Erfolg/Fehler aus

Default `0` bedeutet: Ergebnis nicht sichtbar (opt-in durch Ersteller).

---

## 2. Validierung in `config.php`

`validateSessionData()` wird um das neue Feld erweitert:

```php
// show_results_to_participants: optionaler Boolean, fehlendes Feld = 0
$validatedData['show_results_to_participants'] = isset($postData['show_results_to_participants']) ? 1 : 0;
```

Kein separater Validator nötig — ein fehlendes Checkbox-Feld liefert PHP-seitig schlicht keinen POST-Wert, was sauber als `0` behandelt wird.

---

## 3. UI in `create.php`

Neues `form-group`-Element direkt oberhalb des Submit-Buttons. Styled Toggle-Switch (CSS-Checkbox, kein JS erforderlich), passend zum bestehenden Design-Stil:

```html
<div class="form-group">
    <label>Ergebnisse für Teilnehmende</label>
    <label class="toggle-label">
        <input type="checkbox" name="show_results_to_participants" value="1">
        <span class="toggle-switch"></span>
        Teilnehmende sehen nach dem Absenden das aggregierte Gesamtergebnis aller Einreichungen
    </label>
</div>
```

CSS-Toggle-Switch wird in den bestehenden `<style>`-Block eingefügt (ca. 25 Zeilen, keine externe Abhängigkeit).

Das INSERT in `create.php` wird um das neue Feld erweitert:

```php
INSERT INTO sessions (code, title, description, scale_min, scale_max, chart_color, dimensions, is_active, created_by_admin_id, show_results_to_participants)
VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
```

---

## 4. UI in `edit.php`

Identisches Toggle-Element wie in `create.php`. Der aktuelle DB-Wert wird zur Vorbelegen genutzt:

```php
$checked = $session['show_results_to_participants'] ? 'checked' : '';
```

Das UPDATE wird um das neue Feld erweitert.

---

## 5. Ergebnis-Anzeige in `session.php`

### Logik (PHP, nach dem Submit)

Wenn `$success === true` und `$session['show_results_to_participants'] == 1`:

1. Alle Submissions der Session aus der DB laden
2. Durchschnittswerte pro Dimension berechnen (identische Logik wie `results.php`)
3. Anzahl Teilnehmender ermitteln (inkl. der soeben gespeicherten Stimme)

### HTML-Output

Unterhalb der bestehenden Danke-Meldung erscheint ein neuer Block:

```
┌──────────────────────────────────────────────────────┐
│ ── Gesamtergebnis aller X Teilnehmenden ──────────── │
│                                                      │
│  [Radar-Chart, identisches Styling wie results.php]  │
│                                                      │
│  Dimension 1   ⌀ 7,2  von 10                        │
│  Dimension 2   ⌀ 5,0  von 10                        │
│  ...                                                 │
└──────────────────────────────────────────────────────┘
```

Chart.js ist in `session.php` bereits eingebunden (wird für die Vorschau genutzt) — kein zusätzlicher CDN-Import nötig.

### Wenn Flag nicht gesetzt

Bestehende Danke-Meldung bleibt unverändert:  
`"✅ Vielen Dank! Deine Werte wurden erfolgreich gespeichert. Der Workshop-Leiter kann nun die aggregierten Ergebnisse aller Teilnehmenden einsehen."`

---

## Nicht im Scope

- Echtzeit-Updates (kein AJAX/Polling)
- Anzeige der eigenen Einzelstimme vs. Gesamtergebnis (nur Gesamtergebnis)
- Freitext-Kommentare im Teilnehmer-Ergebnis (nur Zahlenwerte/Chart)
- Zugriffsschutz auf das Ergebnis über den Submit-Zeitpunkt hinaus
