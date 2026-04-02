<?php
require_once 'config.php';
setSecurityHeaders();

$error = '';
$sessionData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    // Rate Limiting: Schutz gegen Brute-Force auf 4-stellige Codes
    // Strenger als Admin-Login: nur 10 Versuche in 15 Minuten
    if (!checkRateLimit('session_code', 10, 900)) {
        $remaining = getRateLimitTimeRemaining('session_code');
        $minutes = ceil($remaining / 60);
        $error = "Zu viele Versuche. Bitte warte {$minutes} Minute(n) und versuche es erneut.";
    } else {
        $code = strtoupper(trim($_POST['code']));

        if (preg_match('/^[0-9]{4}$/', $code)) {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM sessions WHERE code = ? AND is_active = 1");
            $stmt->execute([$code]);
            $sessionData = $stmt->fetch();

            if ($sessionData) {
                // Session gefunden: Rate Limit zurücksetzen
                resetRateLimit('session_code');

                // Weiterleiten zur Session
                header("Location: session.php?code=" . $code);
                exit;
            } else {
                // Session nicht gefunden: Rate Limit erhöhen
                incrementRateLimit('session_code');
                $error = 'Session nicht gefunden oder nicht aktiv.';
            }
        } else {
            $error = 'Bitte gib einen gültigen 4-stelligen Code ein.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kollaboratives Reflexionstool</title>
    <meta name="description" content="Erstelle interaktive Feedbackspinnen für Unterricht, Workshops oder Teams. Kostenlos und ohne Anmeldung.">
  <meta name="keywords" content="Feedbackspinne, Zielscheibe Feedback, Unterricht Feedback Tool">
  <meta name="robots" content="index, follow">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
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
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            color: var(--text);
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 60%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 480px;
            width: 100%;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 32px;
        }
        h1 {
            font-size: 28px;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
            text-align: center;
        }
        .subtitle {
            color: var(--muted);
            text-align: center;
            margin-bottom: 32px;
            line-height: 1.5;
        }
        .form-group {
            margin-bottom: 20px;
        }
        input[type="text"] {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 24px;
            text-align: center;
            letter-spacing: 8px;
            font-weight: 700;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input[type="text"]:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(122,184,0,.18);
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
        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .info {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            color: #075985;
            padding: 12px 16px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 13px;
            line-height: 1.5;
        }
        .admin-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .admin-link a {
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s;
        }
        .admin-link a:hover {
            color: var(--text);
        }
        .logo-wrapper {
            position: relative;
            width: 120px;
            margin: 0 auto 20px auto;
        }
        .logo {
            display: block;
            width: 120px;
            height: 120px;
        }
        .info-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--green);
            color: white;
            border: none;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: background 0.2s;
            z-index: 10;
        }
        .info-btn:hover {
            background: var(--green-2);
        }
        .info-popup {
            display: none;
            position: absolute;
            top: 32px;
            right: -8px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(15,23,42,.12);
            padding: 14px 16px;
            width: 260px;
            font-size: 13px;
            line-height: 1.6;
            color: var(--text);
            z-index: 100;
            text-align: left;
        }
        .info-popup.open {
            display: block;
        }
        .info-popup strong {
            display: block;
            margin-bottom: 6px;
            color: var(--green-2);
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo-wrapper">
                <img src="spider3.svg" alt="Feedbackspinne Logo" class="logo">
                <button class="info-btn" id="infoBtn" aria-label="Test-Zugangsdaten anzeigen">?</button>
                <div class="info-popup" id="infoPopup">
                    <strong>Nur ausprobieren?</strong>
                    Test-Session: <strong>9732</strong><br><br>
                    <strong>Selbst erstellen?</strong>
                    Username: <strong>tester</strong><br>
                    Passwort: <strong>Thekla26!</strong>
                </div>
            </div>
            <h1>Feedback und Reflexion</h1>
            <p class="subtitle">Gib den 4-stelligen Session-Code ein, um teilzunehmen.</p>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="code">Session-Code</label>
                    <input 
                        type="text" 
                        id="code" 
                        name="code" 
                        maxlength="4" 
                        pattern="[0-9]{4}" 
                        placeholder="0000"
                        required
                        autofocus
                    >
                </div>
                <button type="submit">Teilnehmen</button>
            </form>
            
            <div class="info">
                💡 <strong>Hinweis:</strong> Den Session-Code erhältst du von deiner Workshop-Leitung.
            </div>
            
            <div class="admin-link">
                <a href="auth/?view=register">✏️ Konto erstellen</a>
                <a href="auth/">👤 Anmelden</a>
                <a href="impressum.php">Impressum</a>
                <a href="datenschutz.php">Datenschutz</a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-Format Code Input
        const codeInput = document.getElementById('code');
        codeInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
        });

        // Info Popup Toggle
        const infoBtn = document.getElementById('infoBtn');
        const infoPopup = document.getElementById('infoPopup');
        infoBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            infoPopup.classList.toggle('open');
        });
        document.addEventListener('click', function() {
            infoPopup.classList.remove('open');
        });
    </script>
</body>
</html>