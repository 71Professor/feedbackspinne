<?php
require_once 'config.php';

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
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
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
        .logo {
            display: block;
            width: 120px;
            height: 120px;
            margin: 0 auto 20px auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <img src="spider3.svg" alt="Feedbackspinne Logo" class="logo">
            <h1>Feedback und Reflexion</h1>
            <p class="subtitle">Gib den 4-stelligen Session-Code ein, um teilzunehmen. Nur ausprobieren? Test-Session: 9732</p>
            
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
                💡 <strong>Hinweis:</strong> Den Session-Code erhältst du von deinem Workshop-Leiter.
            </div>
            
            <div class="admin-link">
                <a href="admin/">👤 Admin-Bereich</a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-Format Code Input
        const codeInput = document.getElementById('code');
        codeInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
        });
    </script>
</body>
</html>
