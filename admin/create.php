<?php
require_once '../config.php';
requireAdmin();

$success = false;
$error = '';
$generatedCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ungültiger CSRF-Token';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $scaleMin = (int)($_POST['scale_min'] ?? 1);
        $scaleMax = (int)($_POST['scale_max'] ?? 10);
        $chartColor = trim($_POST['chart_color'] ?? '#7ab800');
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
                    $pdo = getDB();
                    $code = generateSessionCode();

                    $stmt = $pdo->prepare("
                        INSERT INTO sessions (code, title, description, scale_min, scale_max, chart_color, dimensions, is_active, created_by_admin_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
                    ");
                    $stmt->execute([
                        $code,
                        $title,
                        $description,
                        $scaleMin,
                        $scaleMax,
                        $chartColor,
                        json_encode($dimensions, JSON_UNESCAPED_UNICODE),
                        $_SESSION['admin_id']
                    ]);

                    $success = true;
                    $generatedCode = $code;
                } catch (Exception $e) {
                    $error = 'Fehler beim Erstellen der Session: ' . $e->getMessage();
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
    <title>Neue Session erstellen</title>
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
        .success-code {
            background: white;
            border: 2px solid #86efac;
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            font-size: 32px;
            font-weight: 800;
            letter-spacing: 4px;
            margin: 16px 0;
            color: #166534;
        }
        .success-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
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
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>✨ Neue Session erstellen</h1>
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
                <h2>✅ Session erfolgreich erstellt!</h2>
                <p>Deine Session wurde erstellt. Teile diesen Code mit deinen Teilnehmenden:</p>
                <div class="success-code"><?php echo htmlspecialchars($generatedCode); ?></div>
                <p><strong>Link für Teilnehmende:</strong><br>
                <?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . '/session.php?code=' . $generatedCode; ?></p>
                <div class="success-actions">
                    <a href="results.php?code=<?php echo $generatedCode; ?>" class="btn btn-primary" style="flex:1;">📈 Ergebnisse anzeigen</a>
                    <a href="create.php" class="btn btn-secondary" style="flex:1;">➕ Weitere Session</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!$success): ?>
            <div class="card">
                <form method="POST" id="sessionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label for="title">Session-Titel *</label>
                        <input type="text" id="title" name="title" required placeholder="z.B. KI in der Kita-Verwaltung">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Beschreibung</label>
                        <textarea id="description" name="description" placeholder="Kurze Beschreibung für die Teilnehmenden"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Skala</label>
                        <div class="scale-inputs">
                            <div>
                                <input type="number" name="scale_min" value="1" min="0" max="10" required>
                                <small style="color: var(--muted);">Min-Wert</small>
                            </div>
                            <div>
                                <input type="number" name="scale_max" value="10" min="1" max="20" required>
                                <small style="color: var(--muted);">Max-Wert</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Diagrammfarbe</label>
                        <div class="color-picker-wrapper">
                            <div class="color-picker-display">
                                <div class="color-preview" id="colorPreview" style="background-color: #7ab800;"></div>
                                <span class="color-label" id="colorLabel">#7ab800</span>
                            </div>
                            <div class="color-options">
                                <div class="color-option selected" style="background-color: #7ab800;" data-color="#7ab800" title="Grün"></div>
                                <div class="color-option" style="background-color: #3B82F6;" data-color="#3B82F6" title="Blau"></div>
                                <div class="color-option" style="background-color: #A855F7;" data-color="#A855F7" title="Lila"></div>
                                <div class="color-option" style="background-color: #EF4444;" data-color="#EF4444" title="Rot"></div>
                                <div class="color-option" style="background-color: #F97316;" data-color="#F97316" title="Orange"></div>
                                <div class="color-option" style="background-color: #22C55E;" data-color="#22C55E" title="Hellgrün"></div>
                                <div class="color-option" style="background-color: #EC4899;" data-color="#EC4899" title="Pink"></div>
                                <div class="color-option" style="background-color: #06B6D4;" data-color="#06B6D4" title="Cyan"></div>
                            </div>
                            <input type="hidden" name="chart_color" id="chartColorInput" value="#7ab800">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Dimensionen (mindestens 3)</label>
                        <div id="dimensions">
                            <!-- Dimensions werden hier per JS eingefügt -->
                        </div>
                        <button type="button" class="btn-add" onclick="addDimension()">+ Dimension hinzufügen</button>
                    </div>
                    
                    <button type="submit" class="btn-primary">Session erstellen</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let dimensionCount = 0;

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

        // Standard-Dimensionen hinzufügen
        addDimension('Datenschutzkonformität', 'unklar/unsicher', 'vollständig gewährleistet');
        addDimension('Zeitersparnis', 'keine Ersparnis', 'erhebliche Ersparnis');
        addDimension('Benutzerfreundlichkeit', 'kompliziert', 'intuitiv bedienbar');

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
