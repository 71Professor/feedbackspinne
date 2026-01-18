<?php
require_once 'config.php';

$code = $_GET['code'] ?? '';
$success = false;
$error = '';

if (!preg_match('/^[0-9]{4}$/', $code)) {
    header('Location: index.php');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM sessions WHERE code = ? AND is_active = 1");
$stmt->execute([$code]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: index.php');
    exit;
}

$dimensions = json_decode($session['dimensions'], true);

// Anzahl bisheriger Teilnehmer
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM submissions WHERE session_id = ?");
$stmt->execute([$session['id']]);
$participantCount = $stmt->fetch()['count'];

// Form verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['values'])) {
    $participantName = trim($_POST['participant_name'] ?? '');
    $values = $_POST['values'] ?? [];
    
    // Validierung
    if (count($values) === count($dimensions)) {
        $validValues = true;
        foreach ($values as $val) {
            if (!is_numeric($val) || $val < $session['scale_min'] || $val > $session['scale_max']) {
                $validValues = false;
                break;
            }
        }
        
        if ($validValues) {
            try {
                $stmt = $pdo->prepare("INSERT INTO submissions (session_id, participant_name, `values`) VALUES (?, ?, ?)");
                $stmt->execute([
                    $session['id'],
                    $participantName,
                    json_encode(array_map('intval', $values))
                ]);
                $success = true;
            } catch (Exception $e) {
                $error = 'Fehler beim Speichern. Bitte versuche es erneut.';
            }
        } else {
            $error = 'Ungültige Werte eingegeben.';
        }
    } else {
        $error = 'Bitte fülle alle Dimensionen aus.';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($session['title']); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --green: #7ab800;
            --green-2: #5e9800;
            --bg: #ffffff;
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
        }
        .wrap {
            max-width: 900px;
            margin: 28px auto;
            padding: 0 16px 28px;
        }
        header {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: clamp(20px, 3vw, 28px);
            letter-spacing: -0.02em;
        }
        .subtitle {
            margin: 0;
            color: var(--muted);
            line-height: 1.5;
        }
        .session-info {
            display: flex;
            gap: 16px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--muted);
        }
        .info-badge {
            background: rgba(122,184,0,.12);
            border: 1px solid rgba(122,184,0,.28);
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 700;
            color: #0b2a00;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px;
        }
        .form-group {
            margin-bottom: 24px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            outline: none;
        }
        input[type="text"]:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(122,184,0,.18);
        }
        .dimension {
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 16px;
            background: #fafafa;
        }
        .dimension-name {
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 12px;
        }
        .poles {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 13px;
            color: var(--muted);
        }
        .slider-container {
            position: relative;
            padding: 12px 0;
        }
        .slider-track {
            position: relative;
            width: 100%;
            height: 6px;
            background: rgba(122,184,0,.22);
            border-radius: 999px;
        }
        input[type="range"] {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            background: transparent;
            position: absolute;
            top: 0;
            left: 0;
            margin: 0;
            cursor: pointer;
        }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 24px;
            height: 24px;
            border-radius: 999px;
            background: var(--green);
            border: 3px solid white;
            box-shadow: 0 4px 12px rgba(15,23,42,.2);
            cursor: pointer;
        }
        input[type="range"]::-moz-range-thumb {
            width: 24px;
            height: 24px;
            border-radius: 999px;
            background: var(--green);
            border: 3px solid white;
            box-shadow: 0 4px 12px rgba(15,23,42,.2);
            cursor: pointer;
        }
        .slider-value {
            position: absolute;
            top: -32px;
            background: var(--green);
            color: white;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            transform: translateX(-50%);
            white-space: nowrap;
        }
        .ticks {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 11px;
            color: var(--muted);
            font-weight: 600;
        }
        button {
            width: 100%;
            padding: 14px 20px;
            background: var(--green);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        button:hover {
            background: var(--green-2);
        }
        button:active {
            transform: translateY(1px);
        }
        .success, .error {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        .chart-preview {
            margin-top: 32px;
            padding-top: 32px;
            border-top: 2px solid var(--border);
        }
        .chart-area {
            width: 100%;
            height: 400px;
            position: relative;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <h1><?php echo htmlspecialchars($session['title']); ?></h1>
            <p class="subtitle"><?php echo htmlspecialchars($session['description']); ?></p>
            <div class="session-info">
                <div class="info-item">
                    <span>Session-Code:</span>
                    <span class="info-badge"><?php echo htmlspecialchars($code); ?></span>
                </div>
                <div class="info-item">
                    <span>Bisherige Teilnehmer:</span>
                    <span class="info-badge"><?php echo $participantCount; ?></span>
                </div>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="success">
                ✅ <strong>Vielen Dank!</strong> Deine Werte wurden erfolgreich gespeichert. 
                Der Workshop-Leiter kann nun die aggregierten Ergebnisse aller Teilnehmenden einsehen.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!$success): ?>
            <div class="card">
                <form method="POST" id="valuesForm">
                    <div class="form-group">
                        <label for="participant_name">Dein Name (optional)</label>
                        <input type="text" id="participant_name" name="participant_name" placeholder="z.B. Max Mustermann">
                    </div>

                    <?php foreach ($dimensions as $index => $dim): ?>
                        <div class="dimension">
                            <div class="dimension-name"><?php echo htmlspecialchars($dim['name']); ?></div>
                            <div class="poles">
                                <span><?php echo htmlspecialchars($dim['left']); ?></span>
                                <span><?php echo htmlspecialchars($dim['right']); ?></span>
                            </div>
                            <div class="slider-container">
                                <div class="slider-track"></div>
                                <div class="slider-value" id="value-<?php echo $index; ?>">
                                    <?php echo floor(($session['scale_min'] + $session['scale_max']) / 2); ?>
                                </div>
                                <input 
                                    type="range" 
                                    name="values[]" 
                                    min="<?php echo $session['scale_min']; ?>" 
                                    max="<?php echo $session['scale_max']; ?>" 
                                    value="<?php echo floor(($session['scale_min'] + $session['scale_max']) / 2); ?>"
                                    step="1"
                                    data-index="<?php echo $index; ?>"
                                >
                            </div>
                            <div class="ticks">
                                <?php for ($i = $session['scale_min']; $i <= $session['scale_max']; $i++): ?>
                                    <span><?php echo $i; ?></span>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit">Werte absenden</button>
                </form>

                <div class="chart-preview">
                    <h3 style="margin: 0 0 16px; font-size: 18px;">Deine Vorschau</h3>
                    <div class="chart-area">
                        <canvas id="previewChart"></canvas>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const dimensions = <?php echo json_encode($dimensions); ?>;
        const scaleMin = <?php echo $session['scale_min']; ?>;
        const scaleMax = <?php echo $session['scale_max']; ?>;
        
        // Slider-Updates
        document.querySelectorAll('input[type="range"]').forEach(slider => {
            const index = slider.dataset.index;
            const valueDisplay = document.getElementById(`value-${index}`);
            
            slider.addEventListener('input', function() {
                valueDisplay.textContent = this.value;
                
                // Position des Value-Badge
                const percent = ((this.value - scaleMin) / (scaleMax - scaleMin)) * 100;
                valueDisplay.style.left = `${percent}%`;
                
                updateChart();
            });
            
            // Initial position
            const percent = ((slider.value - scaleMin) / (scaleMax - scaleMin)) * 100;
            valueDisplay.style.left = `${percent}%`;
        });
        
        // Chart
        const ctx = document.getElementById('previewChart').getContext('2d');
        const previewChart = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: dimensions.map(d => d.name),
                datasets: [{
                    data: Array(dimensions.length).fill(Math.floor((scaleMin + scaleMax) / 2)),
                    backgroundColor: 'rgba(122,184,0,.18)',
                    borderColor: '#7ab800',
                    borderWidth: 3,
                    pointBackgroundColor: '#7ab800',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2.5,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    r: {
                        min: scaleMin,
                        max: scaleMax,
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
        
        function updateChart() {
            const sliders = document.querySelectorAll('input[type="range"]');
            const values = Array.from(sliders).map(s => parseInt(s.value));
            previewChart.data.datasets[0].data = values;
            previewChart.update();
        }
    </script>
</body>
</html>
