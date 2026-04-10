# Participant Results Visibility — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ermöglicht Session-Erstellern festzulegen, ob Teilnehmende nach dem Absenden das aggregierte Gesamtergebnis aller Einreichungen sehen.

**Architecture:** Neues Boolean-Feld `show_results_to_participants` in der `sessions`-Tabelle. Toggle-UI in `create.php` und `edit.php`. In `session.php` wird nach erfolgreichem Submit bei gesetztem Flag das Gesamtergebnis serverseitig berechnet und als Radar-Chart mit Durchschnittsliste ausgegeben.

**Tech Stack:** PHP 8+, MySQL/MariaDB, Chart.js 4.4.1 (bereits eingebunden), CSS-only Toggle-Switch

---

## Dateiübersicht

| Datei | Aktion |
|---|---|
| `migration_add_show_results.sql` | Neu erstellen |
| `run_migration_show_results.php` | Neu erstellen |
| `config.php` | Funktion `validateSessionData()` erweitern |
| `admin/create.php` | CSS + Form-Toggle + INSERT anpassen |
| `admin/edit.php` | CSS + Form-Toggle + UPDATE anpassen |
| `session.php` | PHP-Logik + HTML-Block + JS nach Submit |

---

## Task 1: DB-Migration erstellen und ausführen

**Files:**
- Create: `migration_add_show_results.sql`
- Create: `run_migration_show_results.php`

- [ ] **Schritt 1: SQL-Migrationsdatei anlegen**

Datei `migration_add_show_results.sql` mit folgendem Inhalt erstellen:

```sql
-- Migration: Ergebnis-Sichtbarkeit für Teilnehmende
-- Fügt das Feld show_results_to_participants zur sessions-Tabelle hinzu.
-- Default 0 = Ergebnis nicht sichtbar (Opt-in durch Ersteller).

ALTER TABLE sessions
    ADD COLUMN show_results_to_participants TINYINT(1) NOT NULL DEFAULT 0;
```

- [ ] **Schritt 2: Migrations-Runner anlegen**

Datei `run_migration_show_results.php` mit folgendem Inhalt erstellen:

```php
<?php
require_once 'config.php';

try {
    $pdo = getDB();
    $sql = file_get_contents(__DIR__ . '/migration_add_show_results.sql');
    $pdo->exec($sql);
    echo "Migration erfolgreich: show_results_to_participants hinzugefügt.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Spalte existiert bereits – Migration übersprungen.\n";
    } else {
        echo "Fehler: " . $e->getMessage() . "\n";
    }
}
```

- [ ] **Schritt 3: Migration ausführen**

```bash
php run_migration_show_results.php
```

Erwartete Ausgabe: `Migration erfolgreich: show_results_to_participants hinzugefügt.`

Bei erneutem Aufruf: `Spalte existiert bereits – Migration übersprungen.`

- [ ] **Schritt 4: Commit**

```bash
git add migration_add_show_results.sql run_migration_show_results.php
git commit -m "feat: migration – show_results_to_participants Spalte in sessions"
```

---

## Task 2: `config.php` — `validateSessionData()` erweitern

**Files:**
- Modify: `config.php` (Funktion `validateSessionData`, ca. Zeile 499–609)

- [ ] **Schritt 1: Feld in `validateSessionData()` hinzufügen**

Direkt vor dem abschließenden `return`-Block (nach der Dimensionen-Validierung, vor `if (!empty($errors))`), folgende Zeile einfügen:

```php
    // show_results_to_participants: Checkbox — fehlendes POST-Feld = 0
    $validatedData['show_results_to_participants'] = isset($postData['show_results_to_participants']) ? 1 : 0;
```

Der Block sieht danach so aus (Kontext zur Orientierung):

```php
        // ...
        if (count($dimensions) < 3) {
            $errors[] = 'Mindestens 3 gültige Dimensionen erforderlich.';
        } else {
            $validatedData['dimensions'] = $dimensions;
        }
    }

    // show_results_to_participants: Checkbox — fehlendes POST-Feld = 0
    $validatedData['show_results_to_participants'] = isset($postData['show_results_to_participants']) ? 1 : 0;

    // Return result
    if (!empty($errors)) {
```

- [ ] **Schritt 2: Manuell prüfen**

Keine automatisierten Tests vorhanden. Prüfen durch direkten Aufruf (im Browser oder CLI):

```php
// Kurztest – kann weggeworfen werden, nicht committen
$result = validateSessionData(['show_results_to_participants' => '1', ...]);
var_dump($result['data']['show_results_to_participants']); // int(1)

$result2 = validateSessionData([...]);
var_dump($result2['data']['show_results_to_participants']); // int(0)
```

- [ ] **Schritt 3: Commit**

```bash
git add config.php
git commit -m "feat: validateSessionData – show_results_to_participants auslesen"
```

---

## Task 3: `admin/create.php` — Toggle-UI und INSERT

**Files:**
- Modify: `admin/create.php`

- [ ] **Schritt 1: CSS für Toggle-Switch in `<style>` einfügen**

Am Ende des bestehenden `<style>`-Blocks (vor `</style>`, nach `.color-option.selected { ... }`), folgende CSS-Regeln anhängen:

```css
        .toggle-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            font-weight: normal;
            font-size: 14px;
            color: var(--text);
            user-select: none;
        }
        .toggle-label input[type="checkbox"] {
            width: 0;
            height: 0;
            opacity: 0;
            position: absolute;
        }
        .toggle-track {
            display: inline-block;
            width: 44px;
            height: 24px;
            background: #cbd5e1;
            border-radius: 999px;
            position: relative;
            flex-shrink: 0;
            transition: background 0.2s;
        }
        .toggle-track::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 18px;
            height: 18px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 1px 4px rgba(15,23,42,.2);
            transition: left 0.2s;
        }
        .toggle-label input:checked + .toggle-track {
            background: var(--green);
        }
        .toggle-label input:checked + .toggle-track::after {
            left: 23px;
        }
```

- [ ] **Schritt 2: Form-Group mit Toggle direkt vor dem Submit-Button einfügen**

Den bestehenden `<button type="submit" ...>` suchen und direkt davor einfügen:

```html
                    <div class="form-group">
                        <label>Ergebnisse für Teilnehmende</label>
                        <label class="toggle-label">
                            <input type="checkbox" name="show_results_to_participants" value="1">
                            <span class="toggle-track"></span>
                            Teilnehmende sehen nach dem Absenden das aggregierte Gesamtergebnis aller Einreichungen
                        </label>
                    </div>
```

- [ ] **Schritt 3: INSERT-Statement um das neue Feld erweitern**

Das bestehende `$pdo->prepare("INSERT INTO sessions ...")` ersetzen durch:

```php
                $stmt = $pdo->prepare("
                    INSERT INTO sessions (code, title, description, scale_min, scale_max, chart_color, dimensions, is_active, created_by_admin_id, show_results_to_participants)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
                ");
                $stmt->execute([
                    $code,
                    $data['title'],
                    $data['description'],
                    $data['scale_min'],
                    $data['scale_max'],
                    $data['chart_color'],
                    json_encode($data['dimensions'], JSON_UNESCAPED_UNICODE),
                    $_SESSION['admin_id'],
                    $data['show_results_to_participants'],
                ]);
```

- [ ] **Schritt 4: Manuell prüfen**

1. Im Browser `admin/create.php` aufrufen
2. Session erstellen mit Toggle **an** → in DB prüfen: `SELECT show_results_to_participants FROM sessions ORDER BY id DESC LIMIT 1;` → Wert muss `1` sein
3. Session erstellen mit Toggle **aus** → DB-Wert muss `0` sein

- [ ] **Schritt 5: Commit**

```bash
git add admin/create.php
git commit -m "feat: create.php – Toggle für Ergebnis-Sichtbarkeit der Teilnehmenden"
```

---

## Task 4: `admin/edit.php` — Toggle-UI und UPDATE

**Files:**
- Modify: `admin/edit.php`

- [ ] **Schritt 1: Dieselben CSS-Regeln wie in Task 3 einfügen**

Am Ende des bestehenden `<style>`-Blocks in `edit.php` (nach `.warning-content strong { ... }`), identische Toggle-CSS-Regeln wie in Task 3 Schritt 1 anhängen:

```css
        .toggle-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            font-weight: normal;
            font-size: 14px;
            color: var(--text);
            user-select: none;
        }
        .toggle-label input[type="checkbox"] {
            width: 0;
            height: 0;
            opacity: 0;
            position: absolute;
        }
        .toggle-track {
            display: inline-block;
            width: 44px;
            height: 24px;
            background: #cbd5e1;
            border-radius: 999px;
            position: relative;
            flex-shrink: 0;
            transition: background 0.2s;
        }
        .toggle-track::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 18px;
            height: 18px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 1px 4px rgba(15,23,42,.2);
            transition: left 0.2s;
        }
        .toggle-label input:checked + .toggle-track {
            background: var(--green);
        }
        .toggle-label input:checked + .toggle-track::after {
            left: 23px;
        }
```

- [ ] **Schritt 2: Form-Group mit Toggle direkt vor dem Submit-Button einfügen**

Den bestehenden `<button type="submit" class="btn-primary">Änderungen speichern</button>` suchen und direkt davor einfügen:

```html
                <div class="form-group">
                    <label>Ergebnisse für Teilnehmende</label>
                    <label class="toggle-label">
                        <input type="checkbox" name="show_results_to_participants" value="1"
                            <?php echo !empty($session['show_results_to_participants']) ? 'checked' : ''; ?>>
                        <span class="toggle-track"></span>
                        Teilnehmende sehen nach dem Absenden das aggregierte Gesamtergebnis aller Einreichungen
                    </label>
                </div>
```

- [ ] **Schritt 3: UPDATE-Statement um das neue Feld erweitern**

Das bestehende `$pdo->prepare("UPDATE sessions SET ...")` ersetzen durch:

```php
                $stmt = $pdo->prepare("
                    UPDATE sessions
                    SET title = ?, description = ?, scale_min = ?, scale_max = ?, chart_color = ?, dimensions = ?, show_results_to_participants = ?
                    WHERE id = ? AND created_by_admin_id = ?
                ");
                $stmt->execute([
                    $data['title'],
                    $data['description'],
                    $data['scale_min'],
                    $data['scale_max'],
                    $data['chart_color'],
                    json_encode($data['dimensions'], JSON_UNESCAPED_UNICODE),
                    $data['show_results_to_participants'],
                    $sessionId,
                    $_SESSION['admin_id']
                ]);
```

- [ ] **Schritt 4: Manuell prüfen**

1. Eine bestehende Session in `admin/edit.php` aufrufen
2. Toggle ist entsprechend dem DB-Wert vorbelegt (checked/unchecked)
3. Toggle umschalten und speichern → DB-Wert ändert sich korrekt
4. Seite neu laden → Toggle zeigt den neuen Wert

- [ ] **Schritt 5: Commit**

```bash
git add admin/edit.php
git commit -m "feat: edit.php – Toggle für Ergebnis-Sichtbarkeit der Teilnehmenden"
```

---

## Task 5: `session.php` — Gesamtergebnis nach Submit anzeigen

**Files:**
- Modify: `session.php`

- [ ] **Schritt 1: PHP-Logik zum Berechnen der Ergebnisse nach Submit einfügen**

Direkt nach dem `$success = true;`-Block (nach dem `try/catch`, vor dem schließenden `}`), folgenden Block einfügen. Dieser berechnet die Durchschnittswerte nur wenn nötig:

```php
// Gesamtergebnis laden (nur wenn Teilnehmende es sehen dürfen)
$resultAverages = [];
$resultCount = 0;
if ($success && !empty($session['show_results_to_participants'])) {
    $stmt = $pdo->prepare("SELECT `values` FROM submissions WHERE session_id = ?");
    $stmt->execute([$session['id']]);
    $allSubmissions = $stmt->fetchAll();
    $resultCount = count($allSubmissions);
    if ($resultCount > 0) {
        $sums = array_fill(0, count($dimensions), 0);
        foreach ($allSubmissions as $sub) {
            $vals = json_decode($sub['values'], true);
            foreach ($vals as $i => $val) {
                $sums[$i] += $val;
            }
        }
        foreach ($sums as $i => $sum) {
            $resultAverages[$i] = round($sum / $resultCount, 1);
        }
    }
}
```

Der genaue Einfügepunkt ist am Ende des `if ($_SERVER['REQUEST_METHOD'] === 'POST' ...)` Blocks, nach Zeile 128 (`} // end if POST`).

- [ ] **Schritt 2: HTML-Block für Ergebnis-Anzeige nach der Danke-Meldung einfügen**

Den bestehenden Success-Block (ca. Zeile 431–436 in session.php) suchen:

```php
        <?php if ($success): ?>
            <div class="success">
                ✅ <strong>Vielen Dank!</strong> Deine Werte wurden erfolgreich gespeichert. 
                Der Workshop-Leiter kann nun die aggregierten Ergebnisse aller Teilnehmenden einsehen.
            </div>
        <?php endif; ?>
```

Direkt danach (nach dem `<?php endif; ?>`) einfügen:

```php
        <?php if ($success && !empty($session['show_results_to_participants']) && $resultCount > 0): ?>
            <div class="card" style="margin-top: 20px;">
                <h2 style="margin: 0 0 20px; font-size: 18px;">
                    Gesamtergebnis aller <?php echo $resultCount; ?> Teilnehmenden
                </h2>
                <div style="width: 100%; height: 360px; position: relative;">
                    <canvas id="resultsChart"></canvas>
                </div>
                <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($dimensions as $i => $dim): ?>
                        <div style="border: 1px solid var(--border); border-radius: 12px; padding: 14px; background: #fafafa;">
                            <div style="font-weight: 700; font-size: 14px; margin-bottom: 8px;">
                                <?php echo htmlspecialchars($dim['name']); ?>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
                                <span style="background: var(--green); color: white; padding: 6px 12px; border-radius: 999px; font-weight: 800; font-size: 18px;">
                                    ⌀ <?php echo number_format($resultAverages[$i], 1, ',', '.'); ?>
                                </span>
                                <span style="font-size: 13px; color: var(--muted);">von <?php echo $session['scale_max']; ?></span>
                            </div>
                            <?php if (!empty($dim['left']) || !empty($dim['right'])): ?>
                                <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--muted);">
                                    <span><?php echo htmlspecialchars($dim['left']); ?></span>
                                    <span><?php echo htmlspecialchars($dim['right']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
```

- [ ] **Schritt 3: JS für den Ergebnis-Chart in den bestehenden `<script>`-Block einfügen**

Am Ende des bestehenden `<script>`-Blocks (nach dem Zeichenzähler-Block, vor `</script>`), folgenden Block einfügen:

```php
        <?php if ($success && !empty($session['show_results_to_participants']) && $resultCount > 0): ?>
        const resultsCtx = document.getElementById('resultsChart').getContext('2d');
        new Chart(resultsCtx, {
            type: 'radar',
            data: {
                labels: <?php echo json_encode(array_column($dimensions, 'name')); ?>,
                datasets: [{
                    label: 'Durchschnitt',
                    data: <?php echo json_encode(array_values($resultAverages)); ?>,
                    backgroundColor: 'rgba(<?php echo "{$rgb['r']},{$rgb['g']},{$rgb['b']}"; ?>,.18)',
                    borderColor: '<?php echo $chartColor; ?>',
                    borderWidth: 3,
                    pointBackgroundColor: '<?php echo $chartColor; ?>',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2.5,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'Basierend auf <?php echo $resultCount; ?> Teilnehmer<?php echo $resultCount !== 1 ? "n" : ""; ?>',
                        font: { size: 14, weight: '600' },
                        color: '#64748b'
                    }
                },
                scales: {
                    r: {
                        min: <?php echo $session['scale_min']; ?>,
                        max: <?php echo $session['scale_max']; ?>,
                        ticks: {
                            stepSize: 1,
                            color: '#64748b',
                            font: { size: 12, weight: '700' },
                            backdropColor: 'rgba(255,255,255,.85)',
                        },
                        grid: { color: '#e5e7eb' },
                        angleLines: { color: '#e5e7eb' },
                        pointLabels: {
                            color: '#0f172a',
                            font: { size: 13, weight: '800' },
                        },
                    },
                },
            },
        });
        <?php endif; ?>
```

- [ ] **Schritt 4: Manuell prüfen — Flag AN**

1. Session mit Toggle **an** anlegen (oder per `edit.php` aktivieren)
2. Als Teilnehmender `session.php?code=XXXX` aufrufen
3. Alle Slider setzen und „Werte absenden" klicken
4. Erwartung: Danke-Meldung erscheint, darunter Radar-Chart und Durchschnittsliste mit `⌀ X,X von Y`

- [ ] **Schritt 5: Manuell prüfen — Flag AUS**

1. Session mit Toggle **aus** aufrufen
2. Werte absenden
3. Erwartung: Nur die Danke-Meldung, **kein** Ergebnis-Block

- [ ] **Schritt 6: Commit**

```bash
git add session.php
git commit -m "feat: session.php – aggregiertes Gesamtergebnis nach Submit anzeigen"
```
