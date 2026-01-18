<?php
require_once '../config.php';
requireAdmin();

// Sessions abrufen
$pdo = getDB();
$stmt = $pdo->query("
    SELECT 
        s.*,
        COUNT(sub.id) as submission_count
    FROM sessions s
    LEFT JOIN submissions sub ON s.id = sub.session_id
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$sessions = $stmt->fetchAll();

// Session löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_session'])) {
    if (validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $sessionId = $_POST['session_id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        header('Location: dashboard.php');
        exit;
    }
}

// Session aktivieren/deaktivieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    if (validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $sessionId = $_POST['session_id'] ?? 0;
        $isActive = $_POST['is_active'] ?? 0;
        $stmt = $pdo->prepare("UPDATE sessions SET is_active = ? WHERE id = ?");
        $stmt->execute([$isActive, $sessionId]);
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin-Dashboard</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        h1 {
            margin: 0;
            font-size: 24px;
            letter-spacing: -0.02em;
        }
        .header-actions {
            display: flex;
            gap: 10px;
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
        .btn-primary {
            background: var(--green);
            color: white;
        }
        .btn-primary:hover {
            background: var(--green-2);
        }
        .btn-secondary {
            background: rgba(15,23,42,.04);
            color: var(--text);
            border: 1px solid rgba(15,23,42,.14);
        }
        .btn-danger {
            background: rgba(239,68,68,.06);
            color: #7f1d1d;
            border: 1px solid rgba(239,68,68,.35);
        }
        .sessions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .session-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            position: relative;
        }
        .session-card.inactive {
            opacity: 0.6;
        }
        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }
        .session-code {
            background: rgba(122,184,0,.12);
            border: 1px solid rgba(122,184,0,.28);
            padding: 6px 14px;
            border-radius: 999px;
            font-weight: 800;
            font-size: 18px;
            color: #0b2a00;
            letter-spacing: 2px;
        }
        .session-status {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }
        .status-active {
            background: #dcfce7;
            color: #166534;
        }
        .status-inactive {
            background: #f3f4f6;
            color: #6b7280;
        }
        .session-title {
            font-weight: 700;
            font-size: 18px;
            margin: 8px 0;
        }
        .session-desc {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        .session-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            padding: 12px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            margin-bottom: 16px;
        }
        .meta-item {
            font-size: 13px;
        }
        .meta-label {
            color: var(--muted);
            display: block;
        }
        .meta-value {
            font-weight: 700;
            display: block;
            margin-top: 4px;
        }
        .session-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-small {
            padding: 8px 12px;
            font-size: 13px;
        }
        .empty-state {
            background: white;
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            padding: 60px 20px;
            text-align: center;
        }
        .empty-state h2 {
            margin: 0 0 12px;
            font-size: 20px;
        }
        .empty-state p {
            color: var(--muted);
            margin: 0 0 24px;
        }
        @media (max-width: 768px) {
            .sessions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📊 Session-Verwaltung</h1>
            <div class="header-actions">
                <a href="create.php" class="btn btn-primary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M8 3v10M3 8h10"/></svg>
                    Neue Session
                </a>
                <a href="logout.php" class="btn btn-secondary">Abmelden</a>
            </div>
        </header>

        <?php if (empty($sessions)): ?>
            <div class="empty-state">
                <h2>Noch keine Sessions</h2>
                <p>Erstelle deine erste Session, um zu beginnen.</p>
                <a href="create.php" class="btn btn-primary">Jetzt Session erstellen</a>
            </div>
        <?php else: ?>
            <div class="sessions-grid">
                <?php foreach ($sessions as $session): ?>
                    <div class="session-card <?php echo $session['is_active'] ? '' : 'inactive'; ?>">
                        <div class="session-header">
                            <div class="session-code"><?php echo htmlspecialchars($session['code']); ?></div>
                            <span class="session-status <?php echo $session['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $session['is_active'] ? 'Aktiv' : 'Inaktiv'; ?>
                            </span>
                        </div>
                        
                        <div class="session-title"><?php echo htmlspecialchars($session['title']); ?></div>
                        <div class="session-desc"><?php echo htmlspecialchars(substr($session['description'], 0, 100)); ?><?php echo strlen($session['description']) > 100 ? '...' : ''; ?></div>
                        
                        <div class="session-meta">
                            <div class="meta-item">
                                <span class="meta-label">Teilnehmer</span>
                                <span class="meta-value"><?php echo $session['submission_count']; ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Dimensionen</span>
                                <span class="meta-value"><?php echo count(json_decode($session['dimensions'], true)); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Erstellt</span>
                                <span class="meta-value"><?php echo date('d.m.Y', strtotime($session['created_at'])); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Skala</span>
                                <span class="meta-value"><?php echo $session['scale_min']; ?>-<?php echo $session['scale_max']; ?></span>
                            </div>
                        </div>
                        
                        <div class="session-actions">
                            <a href="results.php?id=<?php echo $session['id']; ?>" class="btn btn-primary btn-small">
                                📈 Ergebnisse
                            </a>
                            <a href="../session.php?code=<?php echo $session['code']; ?>" class="btn btn-secondary btn-small" target="_blank">
                                👁️ Vorschau
                            </a>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                <input type="hidden" name="is_active" value="<?php echo $session['is_active'] ? 0 : 1; ?>">
                                <button type="submit" name="toggle_active" class="btn btn-secondary btn-small">
                                    <?php echo $session['is_active'] ? '⏸️ Deaktivieren' : '▶️ Aktivieren'; ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Session wirklich löschen? Dies kann nicht rückgängig gemacht werden.');">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                <button type="submit" name="delete_session" class="btn btn-danger btn-small">
                                    🗑️ Löschen
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
