<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenschutzerklärung – Feedbackspinne</title>
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
            max-width: 720px;
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
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            margin-top: 32px;
            margin-bottom: 10px;
        }
        h3 {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-top: 20px;
            margin-bottom: 6px;
        }
        p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.75;
            margin-bottom: 12px;
        }
        ul {
            list-style: none;
            padding: 0;
            margin-bottom: 12px;
        }
        ul li {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.75;
            padding-left: 16px;
            position: relative;
        }
        ul li::before {
            content: "–";
            position: absolute;
            left: 0;
            color: var(--green);
        }
        a {
            color: var(--green);
            text-decoration: none;
        }
        a:hover { color: var(--green-2); }
        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 28px 0;
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
            <h1>Datenschutzerklärung</h1>
            <p class="subtitle">Stand: Februar 2026</p>

            <h2>1. Verantwortlicher</h2>
            <p>
                Verantwortlicher im Sinne der Datenschutz-Grundverordnung (DSGVO) ist:<br><br>
                Michael Kohl<br>
                Vilshofener Str. 24<br>
                92286 Rieden<br>
                Deutschland<br><br>
                E-Mail: <a href="mailto:hallo@feedbackspinne.de">hallo@feedbackspinne.de</a>
            </p>

            <hr class="divider">

            <h2>2. Welche Daten werden verarbeitet?</h2>

            <h3>2.1 Automatisch erfasste Zugriffsdaten</h3>
            <p>
                Beim Aufruf der Website erfasst der Webserver automatisch technische Informationen,
                die für den Betrieb der Seite und zum Schutz vor Missbrauch (Rate-Limiting) notwendig
                sind. Dazu gehören insbesondere:
            </p>
            <ul>
                <li>IP-Adresse des anfragenden Geräts</li>
                <li>Datum und Uhrzeit der Anfrage</li>
                <li>Aufgerufene Seite / URL</li>
                <li>HTTP-Statuscode</li>
            </ul>
            <p>
                Die IP-Adresse wird ausschließlich zur Missbrauchsverhinderung (Rate-Limiting)
                verwendet und nicht dauerhaft gespeichert. Rechtsgrundlage ist Art. 6 Abs. 1 lit. f
                DSGVO (berechtigtes Interesse am sicheren Betrieb des Dienstes).
            </p>

            <h3>2.2 Feedback-Daten (Teilnehmende)</h3>
            <p>
                Wenn Teilnehmende einer Workshop-Session Feedback abgeben, werden folgende Daten
                gespeichert:
            </p>
            <ul>
                <li>Optionaler Name (kann freigelassen werden)</li>
                <li>Bewertungswerte auf den eingestellten Skalen (Schieberegler)</li>
                <li>Zeitstempel der Abgabe</li>
                <li>IP-Adresse (zur Verhinderung mehrfacher Abgaben, nicht dauerhaft gespeichert)</li>
            </ul>
            <p>
                Die Erhebung dieser Daten dient der Durchführung der Workshop-Session, für die sich
                Teilnehmende durch Eingabe des Session-Codes aktiv entschieden haben. Rechtsgrundlage
                ist Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an der Auswertung des
                Feedbacks). Die Angabe des Namens ist freiwillig; alle anderen Daten werden
                ausschließlich aggregiert ausgewertet.
            </p>

            <h3>2.3 Nutzerkonten (Workshop-Leitende)</h3>
            <p>
                Workshop-Leitende können ein Konto erstellen. Dabei werden gespeichert:
            </p>
            <ul>
                <li>E-Mail-Adresse</li>
                <li>Passwort (ausschließlich als bcrypt-Hash, nicht im Klartext)</li>
                <li>Zeitpunkt der Registrierung</li>
            </ul>
            <p>
                Diese Daten sind für die Bereitstellung des Dienstes erforderlich. Rechtsgrundlage
                ist Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung).
            </p>

            <h3>2.4 Cookies und Session-Daten</h3>
            <p>
                Feedbackspinne verwendet ausschließlich technisch notwendige Session-Cookies, um den
                Login-Status von Workshop-Leitenden zu verwalten. Es werden keine Tracking-Cookies
                oder Cookies zu Werbezwecken eingesetzt. Das Cookie wird gelöscht, sobald der Browser
                geschlossen oder die Abmeldung vorgenommen wird.
            </p>

            <hr class="divider">

            <h2>3. Weitergabe von Daten an Dritte</h2>
            <p>
                Es findet keine Weitergabe personenbezogener Daten an Dritte statt. Feedbackspinne
                nutzt keine externen Analyse-, Tracking- oder Werbedienste. Es werden keine Daten
                außerhalb der EU/EWR übertragen.
            </p>

            <hr class="divider">

            <h2>4. Speicherdauer</h2>
            <p>
                Personenbezogene Daten werden nur so lange gespeichert, wie es für den jeweiligen
                Zweck erforderlich ist:
            </p>
            <ul>
                <li>Zugriffsdaten / IP-Adressen: temporär während einer Sitzung, kein dauerhaftes Protokoll</li>
                <li>Feedback-Daten: bis zur Löschung der zugehörigen Session durch den Verantwortlichen</li>
                <li>Nutzerkontodaten: bis zur Löschung des Kontos auf Anfrage</li>
            </ul>

            <hr class="divider">

            <h2>5. Ihre Rechte als betroffene Person</h2>
            <p>Sie haben gemäß DSGVO folgende Rechte:</p>
            <ul>
                <li><strong>Auskunft</strong> (Art. 15 DSGVO): Sie können Auskunft über die zu Ihrer Person gespeicherten Daten verlangen.</li>
                <li><strong>Berichtigung</strong> (Art. 16 DSGVO): Sie können die Berichtigung unrichtiger Daten verlangen.</li>
                <li><strong>Löschung</strong> (Art. 17 DSGVO): Sie können die Löschung Ihrer Daten verlangen, soweit keine gesetzliche Aufbewahrungspflicht entgegensteht.</li>
                <li><strong>Einschränkung der Verarbeitung</strong> (Art. 18 DSGVO): Sie können unter bestimmten Voraussetzungen die Einschränkung der Verarbeitung verlangen.</li>
                <li><strong>Datenübertragbarkeit</strong> (Art. 20 DSGVO): Sie können Ihre Daten in einem gängigen, maschinenlesbaren Format erhalten.</li>
                <li><strong>Widerspruch</strong> (Art. 21 DSGVO): Sie können der Verarbeitung Ihrer Daten widersprechen, soweit sie auf Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse) gestützt wird.</li>
            </ul>
            <p>
                Zur Ausübung Ihrer Rechte wenden Sie sich bitte per E-Mail an:
                <a href="mailto:hallo@feedbackspinne.de">hallo@feedbackspinne.de</a>
            </p>

            <hr class="divider">

            <h2>6. Beschwerderecht bei der Aufsichtsbehörde</h2>
            <p>
                Sie haben das Recht, sich bei einer Datenschutz-Aufsichtsbehörde zu beschweren.
                Die zuständige Aufsichtsbehörde für Bayern ist:
            </p>
            <p>
                Bayerisches Landesamt für Datenschutzaufsicht (BayLDA)<br>
                Promenade 27<br>
                91522 Ansbach<br>
                <a href="https://www.lda.bayern.de" target="_blank" rel="noopener noreferrer">www.lda.bayern.de</a>
            </p>
        </div>
    </div>
</body>
</html>
