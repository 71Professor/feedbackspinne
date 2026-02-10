<?php
/**
 * Seed-Datei für weitere Beispiel-Sessions
 *
 * Diese Datei erstellt automatisch zwei Beispiel-Sessions:
 * 1. Workshop-Rahmenbedingungen (Räume, Technik, Catering, etc.)
 * 2. Online-Workshop Demokratiebildung für Jugendliche
 *
 * Aufruf: https://deine-domain.de/seed-example2.php
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
        <title>Weitere Beispiel-Sessions erstellen</title>
        <style>
            body {
                font-family: system-ui, -apple-system, sans-serif;
                max-width: 700px;
                margin: 50px auto;
                padding: 20px;
                background: #f8fafc;
            }
            .card {
                background: white;
                border-radius: 12px;
                padding: 30px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                margin-bottom: 20px;
            }
            h1 { margin-top: 0; color: #0f172a; }
            h2 { color: #0f172a; font-size: 18px; margin-top: 20px; }
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
            .session-box {
                background: #f8fafc;
                border-radius: 8px;
                padding: 20px;
                margin: 15px 0;
            }
            .color-badge {
                display: inline-block;
                width: 20px;
                height: 20px;
                border-radius: 4px;
                vertical-align: middle;
                margin-right: 8px;
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
            <h1>📦 Weitere Beispiel-Sessions</h1>

            <div class="info">
                <strong>📋 Was wird erstellt?</strong><br>
                Es werden <strong>2 fertige Beispiel-Sessions</strong> angelegt:
            </div>

            <div class="session-box">
                <h2>1️⃣ Workshop-Rahmenbedingungen</h2>
                <p><span class="color-badge" style="background: #3b82f6;"></span> Farbe: Blau (#3b82f6)</p>
                <p><strong>Für:</strong> Allgemeines Feedback zu Workshop-Organisation</p>
                <p><strong>Dimensionen (7):</strong></p>
                <ul>
                    <li>Räumlichkeiten (beengend ↔ geräumig/angenehm)</li>
                    <li>Technische Ausstattung (unzureichend ↔ sehr gut)</li>
                    <li>Internet-Verbindung (instabil ↔ stabil/schnell)</li>
                    <li>Catering/Verpflegung (unzureichend ↔ gut)</li>
                    <li>Erreichbarkeit/Anreise (kompliziert ↔ gut erreichbar)</li>
                    <li>Pausen-Organisation (zu kurz ↔ passend)</li>
                    <li>Gesamtatmosphäre (unbequem ↔ angenehm)</li>
                </ul>
            </div>

            <div class="session-box">
                <h2>2️⃣ Online-Workshop Demokratiebildung</h2>
                <p><span class="color-badge" style="background: #9333ea;"></span> Farbe: Lila (#9333ea)</p>
                <p><strong>Für:</strong> Sozialarbeiter & Jugendamt-Mitarbeitende</p>
                <p><strong>Thema:</strong> Demokratiebildung für Jugendliche</p>
                <p><strong>Dimensionen (7):</strong></p>
                <ul>
                    <li>Praxisbezug (theoretisch ↔ praxisnah umsetzbar)</li>
                    <li>Altersgerechte Methoden (schwer vermittelbar ↔ jugendgerecht)</li>
                    <li>Politische Neutralität (einseitig ↔ ausgewogen)</li>
                    <li>Aktivierende Methoden (passiv ↔ partizipativ)</li>
                    <li>Digital umsetzbar (schwer online ↔ gut digital umsetzbar)</li>
                    <li>Diversitätssensibilität (wenig inklusiv ↔ sehr inklusiv)</li>
                    <li>Motivation für Jugendliche (schwer zu begeistern ↔ motivierend)</li>
                </ul>
            </div>

            <div class="warning">
                <strong>⚠️ Wichtig:</strong><br>
                Die Sessions werden dem <strong>ersten Admin-Account</strong> zugeordnet.<br>
                Nach dem Ausführen solltest du diese Datei aus Sicherheitsgründen löschen!
            </div>

            <p>
                <a href="?confirm=ja" class="btn">✅ Ja, Beispiele erstellen</a>
                <a href="admin/" class="btn btn-secondary">❌ Abbrechen</a>
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Beispiel-Sessions erstellen
try {
    $pdo = getDB();

    // Ersten Admin-User finden
    $stmt = $pdo->query("SELECT id FROM admin_users ORDER BY id ASC LIMIT 1");
    $admin = $stmt->fetch();

    if (!$admin) {
        throw new Exception('Kein Admin-User gefunden. Bitte zuerst einen Admin-Account erstellen.');
    }

    $createdSessions = [];

    // =========================================================================
    // SESSION 1: Workshop-Rahmenbedingungen
    // =========================================================================

    $code1 = generateSessionCode();
    $dimensions1 = [
        [
            'name' => 'Räumlichkeiten',
            'left' => 'beengend/unpraktisch',
            'right' => 'geräumig/angenehm'
        ],
        [
            'name' => 'Technische Ausstattung',
            'left' => 'unzureichend',
            'right' => 'sehr gut'
        ],
        [
            'name' => 'Internet-Verbindung',
            'left' => 'instabil/langsam',
            'right' => 'stabil/schnell'
        ],
        [
            'name' => 'Catering/Verpflegung',
            'left' => 'unzureichend',
            'right' => 'ausreichend/gut'
        ],
        [
            'name' => 'Erreichbarkeit/Anreise',
            'left' => 'kompliziert',
            'right' => 'gut erreichbar'
        ],
        [
            'name' => 'Pausen-Organisation',
            'left' => 'zu kurz/ungünstig',
            'right' => 'passend'
        ],
        [
            'name' => 'Gesamtatmosphäre',
            'left' => 'unbequem',
            'right' => 'angenehm'
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO sessions (code, title, description, scale_min, scale_max, chart_color, dimensions, is_active, created_by_admin_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
    ");

    $stmt->execute([
        $code1,
        'Workshop-Rahmenbedingungen',
        'Feedback zu den organisatorischen und räumlichen Rahmenbedingungen des Workshops. Bewerten Sie Räumlichkeiten, Technik, Verpflegung und Erreichbarkeit.',
        1,
        10,
        '#3b82f6',
        json_encode($dimensions1, JSON_UNESCAPED_UNICODE),
        $admin['id']
    ]);

    $createdSessions[] = [
        'title' => 'Workshop-Rahmenbedingungen',
        'code' => $code1,
        'color' => '#3b82f6',
        'dimensions' => count($dimensions1)
    ];

    // =========================================================================
    // SESSION 2: Online-Workshop Demokratiebildung für Jugendliche
    // =========================================================================

    $code2 = generateSessionCode();
    $dimensions2 = [
        [
            'name' => 'Praxisbezug für Jugendarbeit',
            'left' => 'theoretisch',
            'right' => 'praxisnah umsetzbar'
        ],
        [
            'name' => 'Altersgerechte Methoden',
            'left' => 'schwer vermittelbar',
            'right' => 'jugendgerecht'
        ],
        [
            'name' => 'Politische Neutralität',
            'left' => 'zu einseitig',
            'right' => 'ausgewogen/neutral'
        ],
        [
            'name' => 'Aktivierende Methoden',
            'left' => 'passiv/frontal',
            'right' => 'partizipativ/aktivierend'
        ],
        [
            'name' => 'Digital umsetzbar',
            'left' => 'schwer online umsetzbar',
            'right' => 'gut digital umsetzbar'
        ],
        [
            'name' => 'Diversitätssensibilität',
            'left' => 'wenig inklusiv',
            'right' => 'sehr inklusiv'
        ],
        [
            'name' => 'Motivation für Jugendliche',
            'left' => 'schwer zu begeistern',
            'right' => 'motivierend für Jugendliche'
        ]
    ];

    $stmt->execute([
        $code2,
        'Online-Workshop: Demokratiebildung für Jugendliche',
        'Feedback zum Online-Workshop für Sozialarbeiter*innen und Jugendamt-Mitarbeitende. Methoden und Ansätze zur Demokratiebildung in der Jugendarbeit.',
        1,
        10,
        '#9333ea',
        json_encode($dimensions2, JSON_UNESCAPED_UNICODE),
        $admin['id']
    ]);

    $createdSessions[] = [
        'title' => 'Online-Workshop: Demokratiebildung für Jugendliche',
        'code' => $code2,
        'color' => '#9333ea',
        'dimensions' => count($dimensions2)
    ];

    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Beispiele erfolgreich erstellt</title>
        <style>
            body {
                font-family: system-ui, -apple-system, sans-serif;
                max-width: 700px;
                margin: 50px auto;
                padding: 20px;
                background: #f8fafc;
            }
            .card {
                background: white;
                border-radius: 12px;
                padding: 30px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                margin-bottom: 20px;
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
            .session-result {
                background: #f8fafc;
                border-radius: 8px;
                padding: 20px;
                margin: 15px 0;
                border-left: 4px solid;
            }
            .code {
                font-size: 32px;
                font-weight: 800;
                letter-spacing: 6px;
                display: inline-block;
                padding: 10px 20px;
                border-radius: 8px;
                margin: 10px 0;
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
                margin-top: 10px;
            }
            .btn:hover { background: #5e9800; }
            ul { line-height: 1.6; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>✅ Beispiel-Sessions erfolgreich erstellt!</h1>

            <div class="success">
                <strong>Beide Sessions wurden angelegt und sind aktiv.</strong>
            </div>

            <?php foreach ($createdSessions as $session): ?>
                <div class="session-result" style="border-color: <?php echo $session['color']; ?>;">
                    <h2 style="margin-top: 0;"><?php echo htmlspecialchars($session['title']); ?></h2>
                    <div class="code" style="background: <?php echo $session['color']; ?>20; color: <?php echo $session['color']; ?>; border: 2px solid <?php echo $session['color']; ?>;">
                        <?php echo htmlspecialchars($session['code']); ?>
                    </div>
                    <ul>
                        <li>Dimensionen: <?php echo $session['dimensions']; ?> Bereiche</li>
                        <li>Skala: 1-10</li>
                        <li>Farbe: <?php echo $session['color']; ?></li>
                        <li>Status: Aktiv</li>
                    </ul>
                    <a href="session.php?code=<?php echo $session['code']; ?>" class="btn" style="background: <?php echo $session['color']; ?>;" target="_blank">
                        👁️ Session testen
                    </a>
                </div>
            <?php endforeach; ?>

            <div class="warning">
                <strong>🔒 Sicherheitshinweis:</strong><br>
                Bitte lösche jetzt die Datei <code>seed-example2.php</code> vom Server!
            </div>

            <p>
                <a href="admin/dashboard.php" class="btn">📊 Zum Dashboard</a>
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
