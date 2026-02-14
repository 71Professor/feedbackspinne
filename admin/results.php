<?php
require_once '../config.php';
requireAdmin();

// Set security headers
setSecurityHeaders();

$sessionId = $_GET['id'] ?? 0;
$code = $_GET['code'] ?? '';

$pdo = getDB();

// Session finden (entweder per ID oder Code) - nur eigene Sessions
if ($sessionId) {
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ? AND created_by_admin_id = ?");
    $stmt->execute([$sessionId, $_SESSION['admin_id']]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE code = ? AND created_by_admin_id = ?");
    $stmt->execute([$code, $_SESSION['admin_id']]);
}
$session = $stmt->fetch();

if (!$session) {
    // Session nicht gefunden oder gehört nicht dem aktuellen User
    header('Location: dashboard.php');
    exit;
}

$dimensions = json_decode($session['dimensions'], true);
$chartColor = sanitizeChartColor($session['chart_color'] ?? '#7ab800');

// Farbe für CSS-Variablen konvertieren (RGB)
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    return [
        'r' => hexdec(substr($hex, 0, 2)),
        'g' => hexdec(substr($hex, 2, 2)),
        'b' => hexdec(substr($hex, 4, 2))
    ];
}

// Dunklere Farbe für Hover-Effekte (ca. 20% dunkler)
function darkenColor($hex, $percent = 20) {
    $rgb = hexToRgb($hex);
    $r = max(0, min(255, $rgb['r'] * (100 - $percent) / 100));
    $g = max(0, min(255, $rgb['g'] * (100 - $percent) / 100));
    $b = max(0, min(255, $rgb['b'] * (100 - $percent) / 100));
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

$rgb = hexToRgb($chartColor);
$chartColorDark = darkenColor($chartColor);

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
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"
            integrity="sha512-CQBWl4fJHWbryGE+Pc7UAxWMUMNMWzWxF4SQo9CgkJIN1kx6djDQZjh3Y8SZ1d+6I+1zze6Z7kHXO7q3UyZAWw=="
            crossorigin="anonymous"
            referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"
            integrity="sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA=="
            crossorigin="anonymous"
            referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"
            integrity="sha512-qZvrmS2ekKPF2mSznTQsxqPgnpkI4DNTlrdUmTzrDgektczlKNRRhy5X5AAOnx5S09ydFYWWNSfcEqDTTHgtNA=="
            crossorigin="anonymous"
            referrerpolicy="no-referrer"></script>
    <style>
        :root {
            --green: <?php echo $chartColor; ?>;
            --green-2: <?php echo $chartColorDark; ?>;
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
            background: rgba(<?php echo "{$rgb['r']},{$rgb['g']},{$rgb['b']}"; ?>,.12);
            border: 1px solid rgba(<?php echo "{$rgb['r']},{$rgb['g']},{$rgb['b']}"; ?>,.28);
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 700;
            color: var(--text);
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
                <div style="display: flex; gap: 10px;">
                    <a href="best-practice.php" class="btn btn-secondary" target="_blank">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M12 6v6M12 16v.01"/></svg>
                        Best Practice
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary">← Zurück</a>
                </div>
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
            const chartCanvas = resultsChart.canvas;
            const dimensionsData = <?php echo json_encode($dimensions); ?>;
            const scaleMaxVal = <?php echo $session['scale_max']; ?>;

            // Create a composite canvas with chart and averages
            const compositeCanvas = document.createElement('canvas');
            const padding = 40;
            const chartWidth = chartCanvas.width;
            const chartHeight = chartCanvas.height;
            const tableWidth = 500;
            const rowHeight = 90;
            const headerHeight = 60;
            const titleHeight = 120;

            compositeCanvas.width = chartWidth + tableWidth + padding * 3;
            compositeCanvas.height = Math.max(chartHeight, dimensionsData.length * rowHeight + headerHeight) + titleHeight + padding * 2;

            const ctx = compositeCanvas.getContext('2d');

            // White background
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, compositeCanvas.width, compositeCanvas.height);

            // Title
            ctx.fillStyle = '#0f172a';
            ctx.font = 'bold 32px sans-serif';
            ctx.fillText('<?php echo addslashes($session['title']); ?>', padding, padding + 35);

            // Subtitle
            ctx.fillStyle = '#64748b';
            ctx.font = '20px sans-serif';
            ctx.fillText('Durchschnittswerte von <?php echo $counts; ?> Teilnehmer<?php echo $counts !== 1 ? 'n' : ''; ?>', padding, padding + 70);

            // Draw chart
            ctx.drawImage(chartCanvas, padding, titleHeight + padding, chartWidth, chartHeight);

            // Draw averages table
            let tableX = chartWidth + padding * 2;
            let tableY = titleHeight + padding;

            // Table header
            ctx.fillStyle = '#0f172a';
            ctx.font = 'bold 24px sans-serif';
            ctx.fillText('Durchschnittswerte', tableX, tableY + 30);
            tableY += headerHeight;

            // Draw each dimension
            dimensionsData.forEach((dim, i) => {
                // Dimension name
                ctx.fillStyle = '#0f172a';
                ctx.font = 'bold 18px sans-serif';
                ctx.fillText(dim.name, tableX, tableY + 20);

                // Average badge background
                const avgText = '⌀ ' + averages[i].toLocaleString('de-DE', {minimumFractionDigits: 1, maximumFractionDigits: 1});
                ctx.font = 'bold 24px sans-serif';
                const avgWidth = ctx.measureText(avgText).width;

                ctx.fillStyle = '<?php echo $chartColor; ?>';
                ctx.beginPath();
                ctx.roundRect(tableX, tableY + 30, avgWidth + 24, 36, 18);
                ctx.fill();

                // Average text
                ctx.fillStyle = '#ffffff';
                ctx.fillText(avgText, tableX + 12, tableY + 55);

                // "von X" text
                ctx.fillStyle = '#64748b';
                ctx.font = '18px sans-serif';
                ctx.fillText('von ' + scaleMaxVal, tableX + avgWidth + 36, tableY + 55);

                // Pole labels
                ctx.fillStyle = '#64748b';
                ctx.font = '14px sans-serif';
                const poleText = dim.left + ' — ' + dim.right;
                ctx.fillText(poleText, tableX, tableY + 78);

                tableY += rowHeight;
            });

            // Download
            const url = compositeCanvas.toDataURL('image/png');
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
            pdf.setFontSize(20);
            pdf.setFont(undefined, 'bold');
            pdf.setTextColor(15, 23, 42);
            pdf.text('<?php echo addslashes($session['title']); ?>', 40, 45);

            pdf.setFontSize(12);
            pdf.setFont(undefined, 'normal');
            pdf.setTextColor(100, 116, 139);
            pdf.text('Durchschnittswerte von <?php echo $counts; ?> Teilnehmer<?php echo $counts !== 1 ? 'n' : ''; ?>', 40, 65);

            // Chart
            const imgW = canvas.width;
            const imgH = canvas.height;
            const ratio = Math.min((pageW - 80) / imgW, 320 / imgH);
            const w = imgW * ratio;
            const h = imgH * ratio;
            const x = (pageW - w) / 2;
            const y = 85;

            pdf.addImage(imgData, 'PNG', x, y, w, h);

            // Durchschnittswerte Tabelle
            let tableY = y + h + 35;

            pdf.setFontSize(16);
            pdf.setFont(undefined, 'bold');
            pdf.setTextColor(15, 23, 42);
            pdf.text('Durchschnittswerte', 40, tableY);
            tableY += 25;

            const dimensionsData = <?php echo json_encode($dimensions); ?>;
            const scaleMaxVal = <?php echo $session['scale_max']; ?>;

            dimensionsData.forEach((dim, i) => {
                // Check if we need a new page
                if (tableY > pageH - 100) {
                    pdf.addPage();
                    tableY = 50;
                    pdf.setFontSize(16);
                    pdf.setFont(undefined, 'bold');
                    pdf.setTextColor(15, 23, 42);
                    pdf.text('Durchschnittswerte (Fortsetzung)', 40, tableY);
                    tableY += 25;
                }

                // Dimension name (bold)
                pdf.setFontSize(11);
                pdf.setFont(undefined, 'bold');
                pdf.setTextColor(15, 23, 42);
                pdf.text(dim.name, 40, tableY);
                tableY += 15;

                // Average value with colored background
                pdf.setFontSize(14);
                pdf.setFont(undefined, 'bold');
                const avgValue = averages[i].toLocaleString('de-DE', {minimumFractionDigits: 1, maximumFractionDigits: 1});
                const avgText = 'Ø  ' + avgValue;
                const avgWidth = pdf.getTextWidth(avgText);

                // Green badge
                pdf.setFillColor(<?php echo "{$rgb['r']}, {$rgb['g']}, {$rgb['b']}"; ?>);
                pdf.roundedRect(40, tableY - 13, avgWidth + 16, 22, 4, 4, 'F');
                pdf.setTextColor(255, 255, 255);
                pdf.text(avgText, 48, tableY + 2);

                // "von X" text
                pdf.setTextColor(100, 116, 139);
                pdf.setFont(undefined, 'normal');
                pdf.setFontSize(11);
                pdf.text('von ' + scaleMaxVal, 48 + avgWidth + 24, tableY + 1);

                tableY += 18;

                // Pole labels (left and right)
                pdf.setTextColor(100, 116, 139);
                pdf.setFontSize(9);
                pdf.setFont(undefined, 'normal');
                const poleText = dim.left + ' — ' + dim.right;
                pdf.text(poleText, 40, tableY);

                // Reset and add spacing
                pdf.setTextColor(15, 23, 42);
                tableY += 22;
            });

            pdf.save('ergebnisse-<?php echo $session['code']; ?>.pdf');
        }
        <?php endif; ?>
    </script>
</body>
</html>
