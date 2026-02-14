<?php
require_once '../config.php';
requireAdmin();

$success = false;
$error = '';
$sessionId = $_GET['id'] ?? 0;

// Session abrufen (nur eigene Sessions)
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ? AND created_by_admin_id = ?");
$stmt->execute([$sessionId, $_SESSION['admin_id']]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: dashboard.php');
    exit;
}

// Dimensionen dekodieren
$existingDimensions = json_decode($session['dimensions'], true);

// Prüfen, ob bereits Feedback-Daten vorliegen
$stmt = $pdo->prepare("SELECT COUNT(*) as submission_count FROM submissions WHERE session_id = ?");
$stmt->execute([$sessionId]);
$submissionData = $stmt->fetch();
$hasSubmissions = $submissionData['submission_count'] > 0;
$submissionCount = $submissionData['submission_count'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ungültiger CSRF-Token';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $scaleMin = (int)($_POST['scale_min'] ?? 1);
        $scaleMax = (int)($_POST['scale_max'] ?? 10);
        $chartColor = sanitizeChartColor(trim($_POST['chart_color'] ?? '#7ab800'));
        $dimensionNames = $_POST['dimension_names'] ?? [];
        $dimensionLefts = $_POST['dimension_lefts'] ?? [];
        $dimensionRights = $_POST['dimension_rights'] ?? [];

        // Validierung
        if (empty($title)) {
            $error = 'Bitte gib einen Titel ein.';
        } elseif (count($dimensionNames) < 3) {
            $error = 'Mindestens 3 Dimensionen erforderlich.';
        } elseif ($scaleMax <= $scaleMin) {
            $error = 'Max-Wert muss größer als Min-Wert sein.';
        } else {
            // Dimensionen zusammenstellen
            $dimensions = [];
            foreach ($dimensionNames as $i => $name) {
                if (!empty(trim($name))) {
                    $dimensions[] = [
                        'name' => trim($name),
                        'left' => trim($dimensionLefts[$i] ?? ''),
                        'right' => trim($dimensionRights[$i] ?? '')
                    ];
                }
            }

            if (count($dimensions) >= 3) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE sessions
                        SET title = ?, description = ?, scale_min = ?, scale_max = ?, chart_color = ?, dimensions = ?
                        WHERE id = ? AND created_by_admin_id = ?
                    ");
                    $stmt->execute([
                        $title,
                        $description,
                        $scaleMin,
                        $scaleMax,
                        $chartColor,
                        json_encode($dimensions, JSON_UNESCAPED_UNICODE),
                        $sessionId,
                        $_SESSION['admin_id']
                    ]);

                    $success = true;

                    // Session-Daten neu laden
                    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ? AND created_by_admin_id = ?");
                    $stmt->execute([$sessionId, $_SESSION['admin_id']]);
                    $session = $stmt->fetch();
                    $existingDimensions = json_decode($session['dimensions'], true);
                } catch (Exception $e) {
                    $error = 'Fehler beim Aktualisieren der Session: ' . $e->getMessage();
                }
            } else {
                $error = 'Mindestens 3 Dimensionen erforderlich.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session bearbeiten</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <style>
        :root {
            --green: #7ab800;
            --green-2: #5e9800;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e5e7eb;
            --card: #ffffff;
            --shadow: 0 10px 30px rgba(15,23,42,.06);
            --radius: 16px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            color: var(--text);
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 60%);
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        header {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        h1 {
            margin: 0;
            font-size: 24px;
        }
        .btn {
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-secondary {
            background: rgba(15,23,42,.04);
            color: var(--text);
            border: 1px solid rgba(15,23,42,.14);
        }
        .card {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
        }
        .form-group {
            margin-bottom: 24px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        input[type="text"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
        }
        input:focus, textarea:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(122,184,0,.18);
        }
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        .scale-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .dimension-item {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            background: #fafafa;
        }
        .dimension-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .dimension-number {
            font-weight: 800;
            color: var(--muted);
        }
        .btn-remove {
            padding: 6px 10px;
            background: rgba(239,68,68,.06);
            color: #7f1d1d;
            border: 1px solid rgba(239,68,68,.35);
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .poles {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 12px;
        }
        .btn-add {
            width: 100%;
            padding: 12px;
            background: rgba(122,184,0,.08);
            border: 1px dashed rgba(122,184,0,.35);
            color: #0b2a00;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 24px;
        }
        .btn-primary {
            width: 100%;
            padding: 14px 20px;
            background: var(--green);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: var(--green-2);
        }
        .success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .success h2 {
            margin: 0 0 12px;
            font-size: 20px;
        }
        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .color-picker-wrapper {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .color-picker-display {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .color-preview {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            border: 3px solid white;
            box-shadow: 0 0 0 1px var(--border), 0 4px 12px rgba(15,23,42,.1);
        }
        .color-label {
            font-size: 14px;
            color: var(--muted);
        }
        .color-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .color-option {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 3px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(15,23,42,.1);
        }
        .color-option:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(15,23,42,.2);
        }
        .color-option.selected {
            border-color: #0f172a;
            box-shadow: 0 0 0 2px white, 0 0 0 4px #0f172a;
        }
        .session-code-info {
            background: rgba(122,184,0,.08);
            border: 1px solid rgba(122,184,0,.25);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .session-code-info strong {
            font-weight: 800;
            font-size: 18px;
            letter-spacing: 2px;
            color: var(--text);
        }
        .warning {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            color: #92400e;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: start;
            gap: 12px;
        }
        .warning-icon {
            font-size: 24px;
            flex-shrink: 0;
        }
        .warning-content h3 {
            margin: 0 0 8px;
            font-size: 18px;
            color: #92400e;
        }
        .warning-content p {
            margin: 0;
            line-height: 1.5;
        }
        .warning-content strong {
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>✏️ Session bearbeiten</h1>
            <div style="display: flex; gap: 10px;">
                <a href="best-practice.php" class="btn btn-secondary" target="_blank">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M12 6v6M12 16v.01"/></svg>
                    Best Practice
                </a>
                <a href="dashboard.php" class="btn btn-secondary">← Zurück</a>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="success">
                <h2>✅ Session erfolgreich aktualisiert!</h2>
                <p>Die Änderungen wurden gespeichert.</p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="session-code-info">
            <div style="color: var(--muted); font-size: 14px; margin-bottom: 4px;">Session-Code:</div>
            <strong><?php echo htmlspecialchars($session['code']); ?></strong>
        </div>

        <?php if ($hasSubmissions): ?>
            <div class="warning">
                <div class="warning-icon">⚠️</div>
                <div class="warning-content">
                    <h3>Achtung: Feedback-Daten vorhanden</h3>
                    <p>
                        Für diese Session liegen bereits <strong><?php echo $submissionCount; ?> Feedback-Antwort(en)</strong> vor.
                        Änderungen an den Dimensionen oder der Skala können die Auswertung beeinflussen und zu inkonsistenten Ergebnissen führen.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" id="sessionForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="form-group">
                    <label for="title">Session-Titel *</label>
                    <input type="text" id="title" name="title" required placeholder="z.B. KI in der Kita-Verwaltung" value="<?php echo htmlspecialchars($session['title']); ?>">
                </div>

                <div class="form-group">
                    <label for="description">Beschreibung</label>
                    <textarea id="description" name="description" placeholder="Kurze Beschreibung für die Teilnehmenden"><?php echo htmlspecialchars($session['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Skala</label>
                    <div class="scale-inputs">
                        <div>
                            <input type="number" name="scale_min" value="<?php echo $session['scale_min']; ?>" min="0" max="10" required>
                            <small style="color: var(--muted);">Min-Wert</small>
                        </div>
                        <div>
                            <input type="number" name="scale_max" value="<?php echo $session['scale_max']; ?>" min="1" max="20" required>
                            <small style="color: var(--muted);">Max-Wert</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Diagrammfarbe</label>
                    <div class="color-picker-wrapper">
                        <div class="color-picker-display">
                            <div class="color-preview" id="colorPreview" style="background-color: <?php echo htmlspecialchars($session['chart_color']); ?>;"></div>
                            <span class="color-label" id="colorLabel"><?php echo htmlspecialchars($session['chart_color']); ?></span>
                        </div>
                        <div class="color-options">
                            <div class="color-option <?php echo $session['chart_color'] === '#7ab800' ? 'selected' : ''; ?>" style="background-color: #7ab800;" data-color="#7ab800" title="Grün"></div>
                            <div class="color-option <?php echo $session['chart_color'] === '#3B82F6' ? 'selected' : ''; ?>" style="background-color: #3B82F6;" data-color="#3B82F6" title="Blau"></div>
                            <div class="color-option <?php echo $session['chart_color'] === '#A855F7' ? 'selected' : ''; ?>" style="background-color: #A855F7;" data-color="#A855F7" title="Lila"></div>
                            <div class="color-option <?php echo $session['chart_color'] === '#EF4444' ? 'selected' : ''; ?>" style="background-color: #EF4444;" data-color="#EF4444" title="Rot"></div>
                            <div class="color-option <?php echo $session['chart_color'] === '#F97316' ? 'selected' : ''; ?>" style="background-color: #F97316;" data-color="#F97316" title="Orange"></div>
                            <div class="color-option <?php echo $session['chart_color'] === '#22C55E' ? 'selected' : ''; ?>" style="background-color: #22C55E;" data-color="#22C55E" title="Hellgrün"></div>
                            <div class="color-option <?php echo $session['chart_color'] === '#EC4899' ? 'selected' : ''; ?>" style="background-color: #EC4899;" data-color="#EC4899" title="Pink"></div>
                            <div class="color-option <?php echo $session['chart_color'] === '#06B6D4' ? 'selected' : ''; ?>" style="background-color: #06B6D4;" data-color="#06B6D4" title="Cyan"></div>
                        </div>
                        <input type="hidden" name="chart_color" id="chartColorInput" value="<?php echo htmlspecialchars($session['chart_color']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Dimensionen (mindestens 3)</label>
                    <div id="dimensions">
                        <!-- Dimensions werden hier per JS eingefügt -->
                    </div>
                    <button type="button" class="btn-add" onclick="addDimension()">+ Dimension hinzufügen</button>
                </div>

                <button type="submit" class="btn-primary">Änderungen speichern</button>
            </form>
        </div>
    </div>

    <script>
        let dimensionCount = 0;
        const existingDimensions = <?php echo json_encode($existingDimensions); ?>;

        function addDimension(name = '', left = '', right = '') {
            dimensionCount++;
            const container = document.getElementById('dimensions');
            const div = document.createElement('div');
            div.className = 'dimension-item';
            div.id = `dim-${dimensionCount}`;
            div.innerHTML = `
                <div class="dimension-header">
                    <span class="dimension-number">Dimension ${dimensionCount}</span>
                    <button type="button" class="btn-remove" onclick="removeDimension(${dimensionCount})">✕ Entfernen</button>
                </div>
                <input type="text" name="dimension_names[]" placeholder="Name der Dimension" value="${name}" required>
                <div class="poles">
                    <input type="text" name="dimension_lefts[]" placeholder="Linker Pol" value="${left}">
                    <input type="text" name="dimension_rights[]" placeholder="Rechter Pol" value="${right}">
                </div>
            `;
            container.appendChild(div);
        }

        function removeDimension(id) {
            const dim = document.getElementById(`dim-${id}`);
            if (dim) {
                dim.remove();
                updateDimensionNumbers();
            }
        }

        function updateDimensionNumbers() {
            const dims = document.querySelectorAll('.dimension-item');
            dims.forEach((dim, index) => {
                dim.querySelector('.dimension-number').textContent = `Dimension ${index + 1}`;
            });
        }

        // Vorhandene Dimensionen laden
        existingDimensions.forEach(dim => {
            addDimension(dim.name, dim.left, dim.right);
        });

        // Farbpicker-Funktionalität
        const colorOptions = document.querySelectorAll('.color-option');
        const colorPreview = document.getElementById('colorPreview');
        const colorLabel = document.getElementById('colorLabel');
        const chartColorInput = document.getElementById('chartColorInput');

        colorOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Entferne "selected" von allen Optionen
                colorOptions.forEach(opt => opt.classList.remove('selected'));

                // Füge "selected" zur geklickten Option hinzu
                this.classList.add('selected');

                // Aktualisiere die Vorschau und das Input-Feld
                const color = this.getAttribute('data-color');
                colorPreview.style.backgroundColor = color;
                colorLabel.textContent = color;
                chartColorInput.value = color;
            });
        });
    </script>
</body>
</html>
