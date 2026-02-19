<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impressum – Feedbackspinne</title>
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
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            color: var(--text);
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 60%);
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 20px;
        }
        .container {
            max-width: 640px;
            width: 100%;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--muted);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 24px;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--text); }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 40px;
        }
        h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 8px;
        }
        .subtitle {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 32px;
        }
        h2 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
            margin-top: 28px;
            margin-bottom: 8px;
        }
        p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }
        a {
            color: var(--green);
            text-decoration: none;
        }
        a:hover { color: var(--green-2); }
        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 24px 0;
        }
        @media (max-width: 480px) {
            .card { padding: 24px; }
            h1 { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Zurück zur Startseite</a>

        <div class="card">
            <h1>Impressum</h1>
            <p class="subtitle">Angaben gemäß § 5 TMG</p>

            <h2>Betreiber</h2>
            <p>
                Michael Kohl<br>
                Vilshofener Str. 24<br>
                92286 Rieden<br>
                Deutschland
            </p>

            <h2>Kontakt</h2>
            <p>E-Mail: <a href="mailto:hallo@feedbackspinne.de">hallo@feedbackspinne.de</a></p>

            <hr class="divider">

            <h2>Haftungsausschluss</h2>
            <p>
                Die Inhalte dieser Website wurden mit größtmöglicher Sorgfalt erstellt. Für die
                Richtigkeit, Vollständigkeit und Aktualität der Inhalte kann ich jedoch keine Gewähr
                übernehmen. Als Diensteanbieter bin ich gemäß § 7 Abs. 1 TMG für eigene Inhalte auf
                diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG bin
                ich als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte
                fremde Informationen zu überwachen.
            </p>

            <h2>Umsatzsteuer</h2>
            <p>
                Ich bin Kleinunternehmer im Sinne von § 19 UStG und weise daher keine
                Umsatzsteuer aus.
            </p>
        </div>
    </div>
</body>
</html>
