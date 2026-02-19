<?php
require_once '../config.php';
setSecurityHeaders();

$error = '';

if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate Limiting prüfen (Schutz gegen Brute-Force)
    if (!checkRateLimit('admin_login', 5, 900)) {
        $remaining = getRateLimitTimeRemaining('admin_login');
        $minutes = ceil($remaining / 60);
        $error = "Zu viele fehlgeschlagene Login-Versuche. Bitte warte {$minutes} Minute(n) und versuche es erneut.";
    }
    // CSRF-Token validieren (Schutz gegen Login CSRF)
    elseif (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ungültige Anfrage. Bitte lade die Seite neu und versuche es erneut.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($username && $password) {
            $pdo = getDB();
            $adminId = null;
            $adminUsername = null;
            $loginSuccess = false;

            // 1. Zuerst admin_users prüfen (bestehende Admin-Konten)
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            $adminUser = $stmt->fetch();

            if ($adminUser && password_verify($password, $adminUser['password_hash'])) {
                $adminId = $adminUser['id'];
                $adminUsername = $adminUser['username'];
                $loginSuccess = true;
            }

            // 2. Fallback: users-Tabelle prüfen (via /auth/ registrierte Nutzer)
            if (!$loginSuccess) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1");
                $stmt->execute([$username, $username]);
                $regularUser = $stmt->fetch();

                if ($regularUser && password_verify($password, $regularUser['password_hash'])) {
                    // Admin-Eintrag für diesen Nutzer anlegen falls noch nicht vorhanden
                    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
                    $stmt->execute([$regularUser['username']]);
                    $linkedAdmin = $stmt->fetch();

                    if (!$linkedAdmin) {
                        // Platzhalter-Hash: kann nie mit password_verify übereinstimmen
                        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?)");
                        $stmt->execute([$regularUser['username'], '!!auth_user!!']);
                        $adminId = (int)$pdo->lastInsertId();
                    } else {
                        $adminId = $linkedAdmin['id'];
                    }

                    $adminUsername = $regularUser['username'];
                    $loginSuccess = true;
                }
            }

            if ($loginSuccess) {
                // Login erfolgreich: Rate Limit zurücksetzen
                resetRateLimit('admin_login');

                // Session-Regeneration (Schutz gegen Session Fixation)
                session_regenerate_id(true);

                $_SESSION[ADMIN_SESSION_NAME] = true;
                $_SESSION['admin_id'] = $adminId;
                $_SESSION['admin_username'] = $adminUsername;
                $_SESSION['last_activity'] = time(); // Für Session-Timeout

                header('Location: dashboard.php');
                exit;
            } else {
                // Login fehlgeschlagen: Rate Limit erhöhen
                incrementRateLimit('admin_login');
                $error = 'Ungültige Anmeldedaten.';
            }
        } else {
            $error = 'Bitte fülle alle Felder aus.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin-Login</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
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
            max-width: 420px;
            width: 100%;
        }
        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15,23,42,.06);
            padding: 32px;
        }
        h1 {
            font-size: 24px;
            margin: 0 0 8px;
            text-align: center;
        }
        .subtitle {
            color: #64748b;
            text-align: center;
            margin-bottom: 32px;
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
        input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            outline: none;
        }
        input:focus {
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
            line-height: 1.6;
        }
        .nav-links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            font-size: 13px;
            color: var(--muted);
        }
        .nav-links a {
            color: var(--green);
            text-decoration: none;
            font-weight: 600;
        }
        .nav-links a:hover {
            color: var(--green-2);
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Admin-Login</h1>
            <p class="subtitle">Melde dich an, um Feedback-Sessions zu erstellen und zu verwalten.</p>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="username">Benutzername oder E-Mail</label>
                    <input type="text" id="username" name="username" required autofocus autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label for="password">Passwort</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit">Anmelden</button>
            </form>

            <div class="nav-links">
                Noch kein Konto? <a href="../auth/">Jetzt registrieren</a>
            </div>

        </div>
    </div>
</body>
</html>
