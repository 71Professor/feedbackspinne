<?php
/**
 * Seed-Datei für Pixelwerkstatt-Beispiel
 *
 * Diese Datei erstellt automatisch eine Beispiel-Session für einen
 * Workshop-Feedback-Tag in der Pixelwerkstatt Bayern.
 *
 * Aufruf: https://deine-domain.de/seed-example.php
 *
 * WICHTIG: Nach dem Ausführen diese Datei aus Sicherheitsgründen löschen!
 */

require_once 'config.php';

// Sicherheitsabfrage: Nur mit Bestätigung ausführen
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'ja';

if (!$confirmed) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Beispiel-Session erstellen</title>
        <style>
            body {
                font-family: system-ui, -apple-system, sans-serif;
                max-width: 600px;
                margin: 50px auto;
                padding: 20px;
                background: #f8fafc;
            }
            .card {
                background: white;
                border-radius: 12px;
                padding: 30px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            h1 { margin-top: 0; color: #0f172a; }
            .warning {
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
            }
            .info {
                background: #dbeafe;
                border-left: 4px solid #3b82f6;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
            }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background: #7ab800;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                margin-right: 10px;
            }
            .btn:hover { background: #5e9800; }
            .btn-secondary {
                background: #64748b;
            }
            .btn-secondary:hover { background: #475569; }
            ul { line-height: 1.8; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>🌱 Pixelwerkstatt Beispiel-Session</h1>

            <div class="info">
                <strong>📋 Was wird erstellt?</strong><br>
                Eine fertige Beispiel-Session mit dem Thema:<br>
                <em>"Digitalisierung in der Kita - Workshop Pixelwerkstatt"</em>
            </div>

            <p><strong>Enthält folgende Dimensionen:</strong></p>
            <ul>
                <li><strong>Praxistauglichkeit</strong> (theoretisch ↔ direkt umsetzbar)</li>
                <li><strong>Verständlichkeit</strong> (überfordernd ↔ gut nachvollziehbar)</li>
                <li><strong>Relevanz für Kita-Alltag</strong> (wenig ↔ sehr relevant)</li>
                <li><strong>Technische Umsetzbarkeit</strong> (zu komplex ↔ machbar)</li>
                <li><strong>Motivation zur Umsetzung</strong> (skeptisch ↔ motiviert)</li>
                <li><strong>Zeitaufwand realistisch</strong> (zu zeitintensiv ↔ umsetzbar)</li>
                <li><strong>Mehrwert für Kinder</strong> (wenig ↔ großer Mehrwert)</li>
            </ul>

            <div class="warning">
                <strong>⚠️ Wichtig:</strong><br>
                Die Session wird dem <strong>ersten Admin-Account</strong> zugeordnet.<br>
                Nach dem Ausführen solltest du diese Datei aus Sicherheitsgründen löschen!
            </div>

            <p>
                <a href="?confirm=ja" class="btn">✅ Ja, Beispiel erstellen</a>
                <a href="admin/" class="btn btn-secondary">❌ Abbrechen</a>
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Beispiel-Session erstellen
try {
    $pdo = getDB();

    // Ersten Admin-User finden
    $stmt = $pdo->query("SELECT id FROM admin_users ORDER BY id ASC LIMIT 1");
    $admin = $stmt->fetch();

    if (!$admin) {
        throw new Exception('Kein Admin-User gefunden. Bitte zuerst einen Admin-Account erstellen.');
    }

    // Session-Code generieren
    $code = generateSessionCode();

    // Dimensionen definieren
    $dimensions = [
        [
            'name' => 'Praxistauglichkeit',
            'left' => 'theoretisch/abstrakt',
            'right' => 'direkt im Alltag umsetzbar'
        ],
        [
            'name' => 'Verständlichkeit der Inhalte',
            'left' => 'überfordernd',
            'right' => 'gut nachvollziehbar'
        ],
        [
            'name' => 'Relevanz für meinen Kita-Alltag',
            'left' => 'wenig relevant',
            'right' => 'sehr relevant'
        ],
        [
            'name' => 'Technische Umsetzbarkeit',
            'left' => 'zu komplex für uns',
            'right' => 'technisch machbar'
        ],
        [
            'name' => 'Motivation zur Umsetzung',
            'left' => 'unsicher/skeptisch',
            'right' => 'motiviert loszulegen'
        ],
        [
            'name' => 'Zeitaufwand realistisch',
            'left' => 'zu zeitintensiv',
            'right' => 'zeitlich umsetzbar'
        ],
        [
            'name' => 'Mehrwert für die Kinder',
            'left' => 'wenig Mehrwert',
            'right' => 'großer Mehrwert'
        ]
    ];

    // Session in Datenbank einfügen
    $stmt = $pdo->prepare("
        INSERT INTO sessions (code, title, description, scale_min, scale_max, chart_color, dimensions, is_active, created_by_admin_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
    ");

    $stmt->execute([
        $code,
        'Digitalisierung in der Kita - Workshop Pixelwerkstatt',
        'Workshop-Feedback zum Thema digitale Medien und Tools im Kita-Alltag. Durchgeführt in der Pixelwerkstatt Bayern - https://www.pixelwerkstatt.kita.bayern/',
        1,
        10,
        '#7ab800',
        json_encode($dimensions, JSON_UNESCAPED_UNICODE),
        $admin['id']
    ]);

    $sessionId = $pdo->lastInsertId();

    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Beispiel erfolgreich erstellt</title>
        <style>
            body {
                font-family: system-ui, -apple-system, sans-serif;
                max-width: 600px;
                margin: 50px auto;
                padding: 20px;
                background: #f8fafc;
            }
            .card {
                background: white;
                border-radius: 12px;
                padding: 30px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            h1 { margin-top: 0; color: #0f172a; }
            .success {
                background: #dcfce7;
                border-left: 4px solid #16a34a;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
                color: #166534;
            }
            .code-box {
                background: #f8fafc;
                border: 2px solid #7ab800;
                border-radius: 8px;
                padding: 20px;
                text-align: center;
                margin: 20px 0;
            }
            .code {
                font-size: 48px;
                font-weight: 800;
                letter-spacing: 8px;
                color: #7ab800;
            }
            .warning {
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
            }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background: #7ab800;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                margin-right: 10px;
            }
            .btn:hover { background: #5e9800; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>✅ Beispiel-Session erfolgreich erstellt!</h1>

            <div class="success">
                <strong>Die Pixelwerkstatt-Session wurde angelegt und ist aktiv.</strong>
            </div>

            <div class="code-box">
                <div style="font-size: 14px; color: #64748b; margin-bottom: 10px;">Session-Code:</div>
                <div class="code"><?php echo htmlspecialchars($code); ?></div>
            </div>

            <p><strong>Details:</strong></p>
            <ul>
                <li>Titel: Digitalisierung in der Kita - Workshop Pixelwerkstatt</li>
                <li>Dimensionen: 7 Bereiche</li>
                <li>Skala: 1-10</li>
                <li>Farbe: Pixelwerkstatt-Grün (#7ab800)</li>
                <li>Status: Aktiv</li>
            </ul>

            <div class="warning">
                <strong>🔒 Sicherheitshinweis:</strong><br>
                Bitte lösche jetzt die Datei <code>seed-example.php</code> vom Server!
            </div>

            <p>
                <a href="admin/dashboard.php" class="btn">📊 Zum Dashboard</a>
                <a href="session.php?code=<?php echo $code; ?>" class="btn" target="_blank">👁️ Session testen</a>
            </p>
        </div>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Fehler</title>
        <style>
            body {
                font-family: system-ui, -apple-system, sans-serif;
                max-width: 600px;
                margin: 50px auto;
                padding: 20px;
                background: #f8fafc;
            }
            .card {
                background: white;
                border-radius: 12px;
                padding: 30px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            h1 { margin-top: 0; color: #0f172a; }
            .error {
                background: #fee2e2;
                border-left: 4px solid #ef4444;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
                color: #991b1b;
            }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background: #64748b;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
            }
            .btn:hover { background: #475569; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>❌ Fehler</h1>

            <div class="error">
                <strong>Es ist ein Fehler aufgetreten:</strong><br>
                <?php echo htmlspecialchars($e->getMessage()); ?>
            </div>

            <p>
                <a href="admin/" class="btn">Zurück zum Admin-Bereich</a>
            </p>
        </div>
    </body>
    </html>
    <?php
}
