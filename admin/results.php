<?php
require_once '../config.php';
requireAdmin();

$sessionId = $_GET['id'] ?? 0;
$code = $_GET['code'] ?? '';

$pdo = getDB();

// Session finden (entweder per ID oder Code)
if ($sessionId) {
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE code = ?");
    $stmt->execute([$code]);
}
$session = $stmt->fetch();

if (!$session) {
    header('Location: dashboard.php');
    exit;
}

$dimensions = json_decode($session['dimensions'], true);

// Alle Submissions für diese Session abrufen
$stmt = $pdo->prepare("
    SELECT participant_name, `values`, submitted_at 
    FROM submissions 
    WHERE session_id = ?
    ORDER BY submitted_at DESC
");
$stmt->execute([$session['id']]);
$submissions = $stmt->fetchAll();

// Durchschnittswerte berechnen
$averages = [];
$counts = count($submissions);

if ($counts > 0) {
    $sums = array_fill(0, count($dimensions), 0);
    
    foreach ($submissions as $sub) {
        $values = json_decode($sub['values'], true);
        foreach ($values as $i => $val) {
            $sums[$i] += $val;
        }
    }
    
    foreach ($sums as $i => $sum) {
        $averages[$i] = round($sum / $counts, 1);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ergebnisse: <?php echo htmlspecialchars($session['title']); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
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
            max-width: 1200px;
            margin: 0 auto;
        }
        header {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
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
        .btn-primary {
            background: var(--green);
            color: white;
        }
        .session-meta {
            display: flex;
            gap: 24px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            flex-wrap: wrap;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .meta-value {
            background: rgba(122,184,0,.12);
            border: 1px solid rgba(122,184,0,.28);
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 700;
            color: #0b2a00;
        }
        .grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .card {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
        }
        .card h2 {
            margin: 0 0 20px;
            font-size: 18px;
        }
        .chart-area {
            width: 100%;
            height: 500px;
            position: relative;
        }
        .dimension-results {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .dim-result {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
            background: #fafafa;
        }
        .dim-name {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .dim-avg {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .avg-badge {
            background: var(--green);
            color: white;
            padding: 6px 12px;
            border-radius: 999px;
            font-weight: 800;
            font-size: 18px;
        }
        .dim-poles {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--muted);
        }
        .participants-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .participant {
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 10px;
            background: #fafafa;
        }
        .participant-name {
            font-weight: 700;
            margin-bottom: 8px;
        }
        .participant-date {
            font-size: 12px;
            color: var(--muted);
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        @media (max-width: 968px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-top">
                <h1>📊 <?php echo htmlspecialchars($session['title']); ?></h1>
                <a href="dashboard.php" class="btn btn-secondary">← Zurück</a>
            </div>
            <div class="session-meta">
                <div class="meta-item">
                    <span>Session-Code:</span>
                    <span class="meta-value"><?php echo htmlspecialchars($session['code']); ?></span>
                </div>
                <div class="meta-item">
                    <span>Teilnehmer:</span>
                    <span class="meta-value"><?php echo $counts; ?></span>
                </div>
                <div class="meta-item">
                    <span>Status:</span>
                    <span class="meta-value"><?php echo $session['is_active'] ? 'Aktiv' : 'Inaktiv'; ?></span>
                </div>
            </div>
        </header>

        <?php if ($counts === 0): ?>
            <div class="card">
                <div class="empty-state">
                    <h2>Noch keine Teilnehmer</h2>
                    <p>Es wurden noch keine Werte eingereicht. Teile den folgenden Link mit deinen Teilnehmenden:</p>
                    <div style="background: rgba(122,184,0,.08); padding: 16px; border-radius: 12px; margin: 20px 0; word-break: break-all;">
                        <?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . '/session.php?code=' . $session['code']; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="grid">
                <div class="card">
                    <h2>Durchschnittliche Bewertung aller Teilnehmenden</h2>
                    <div class="chart-area">
                        <canvas id="resultsChart"></canvas>
                    </div>
                    <div class="export-buttons">
                        <button onclick="exportPNG()" class="btn btn-primary">💾 Als PNG speichern</button>
                        <button onclick="exportPDF()" class="btn btn-secondary">📄 Als PDF speichern</button>
                    </div>
                </div>

                <div class="card">
                    <h2>Durchschnittswerte</h2>
                    <div class="dimension-results">
                        <?php foreach ($dimensions as $i => $dim): ?>
                            <div class="dim-result">
                                <div class="dim-name"><?php echo htmlspecialchars($dim['name']); ?></div>
                                <div class="dim-avg">
                                    <span class="avg-badge">⌀ <?php echo number_format($averages[$i], 1, ',', '.'); ?></span>
                                    <span style="font-size: 13px; color: var(--muted);">von <?php echo $session['scale_max']; ?></span>
                                </div>
                                <div class="dim-poles">
                                    <span><?php echo htmlspecialchars($dim['left']); ?></span>
                                    <span><?php echo htmlspecialchars($dim['right']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Eingereichte Bewertungen (<?php echo $counts; ?>)</h2>
                <div class="participants-list">
                    <?php foreach ($submissions as $sub): ?>
                        <div class="participant">
                            <div class="participant-name">
                                <?php echo $sub['participant_name'] ? htmlspecialchars($sub['participant_name']) : 'Anonym'; ?>
                            </div>
                            <div class="participant-date">
                                <?php echo date('d.m.Y H:i', strtotime($sub['submitted_at'])); ?> Uhr
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const dimensions = <?php echo json_encode(array_column($dimensions, 'name')); ?>;
        const averages = <?php echo json_encode($averages); ?>;
        const scaleMin = <?php echo $session['scale_min']; ?>;
        const scaleMax = <?php echo $session['scale_max']; ?>;

        <?php if ($counts > 0): ?>
        const ctx = document.getElementById('resultsChart').getContext('2d');
        const resultsChart = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: dimensions,
                datasets: [{
                    label: 'Durchschnitt',
                    data: averages,
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
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'Basierend auf <?php echo $counts; ?> Teilnehmer<?php echo $counts !== 1 ? 'n' : ''; ?>',
                        font: { size: 14, weight: '600' },
                        color: '#64748b'
                    }
                },
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

        async function exportPNG() {
            const canvas = resultsChart.canvas;
            const url = canvas.toDataURL('image/png');
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ergebnisse-<?php echo $session['code']; ?>.png';
            a.click();
        }

        async function exportPDF() {
            const canvas = resultsChart.canvas;
            const imgData = canvas.toDataURL('image/png');
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'a4' });
            
            const pageW = pdf.internal.pageSize.getWidth();
            const pageH = pdf.internal.pageSize.getHeight();
            
            // Titel
            pdf.setFontSize(18);
            pdf.text('<?php echo addslashes($session['title']); ?>', 40, 40);
            pdf.setFontSize(12);
            pdf.text('Durchschnittswerte von <?php echo $counts; ?> Teilnehmer<?php echo $counts !== 1 ? 'n' : ''; ?>', 40, 60);
            
            // Chart
            const imgW = canvas.width;
            const imgH = canvas.height;
            const ratio = Math.min((pageW - 80) / imgW, (pageH - 150) / imgH);
            const w = imgW * ratio;
            const h = imgH * ratio;
            const x = (pageW - w) / 2;
            const y = 80;
            
            pdf.addImage(imgData, 'PNG', x, y, w, h);
            pdf.save('ergebnisse-<?php echo $session['code']; ?>.pdf');
        }
        <?php endif; ?>
    </script>
</body>
</html>
