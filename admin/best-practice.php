<?php
require_once '../config.php';
requireAdmin();

// Markdown-Datei laden
$markdownFile = '../FEEDBACK_DESIGN_GUIDE.md';
$markdownContent = file_exists($markdownFile) ? file_get_contents($markdownFile) : '# Fehler\n\nDie Anleitung konnte nicht geladen werden.';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Best Practice - Feedback-Gestaltung</title>
    <script src="https://cdn.jsdelivr.net/npm/marked@11.1.1/marked.min.js"></script>
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
            line-height: 1.6;
        }
        .container {
            max-width: 900px;
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
        .btn-secondary:hover {
            background: rgba(15,23,42,.08);
        }
        .content {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 40px;
            box-shadow: var(--shadow);
        }

        /* Markdown-Styling */
        #markdown-content h1 {
            font-size: 2em;
            margin: 1em 0 0.5em 0;
            padding-bottom: 0.3em;
            border-bottom: 2px solid var(--green);
            color: var(--text);
        }
        #markdown-content h2 {
            font-size: 1.5em;
            margin: 1.5em 0 0.5em 0;
            color: var(--text);
            padding-top: 0.5em;
        }
        #markdown-content h3 {
            font-size: 1.25em;
            margin: 1.2em 0 0.5em 0;
            color: var(--green-2);
        }
        #markdown-content p {
            margin: 1em 0;
            color: var(--text);
        }
        #markdown-content ul, #markdown-content ol {
            margin: 1em 0;
            padding-left: 2em;
        }
        #markdown-content li {
            margin: 0.5em 0;
        }
        #markdown-content code {
            background: rgba(122, 184, 0, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9em;
            font-family: 'Courier New', monospace;
        }
        #markdown-content pre {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            overflow-x: auto;
            margin: 1em 0;
        }
        #markdown-content pre code {
            background: none;
            padding: 0;
        }
        #markdown-content blockquote {
            border-left: 4px solid var(--green);
            padding-left: 1em;
            margin: 1em 0;
            color: var(--muted);
            font-style: italic;
        }
        #markdown-content hr {
            border: none;
            border-top: 2px solid var(--border);
            margin: 2em 0;
        }
        #markdown-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1em 0;
        }
        #markdown-content table th,
        #markdown-content table td {
            border: 1px solid var(--border);
            padding: 8px 12px;
            text-align: left;
        }
        #markdown-content table th {
            background: rgba(122, 184, 0, 0.1);
            font-weight: 600;
        }
        #markdown-content a {
            color: var(--green-2);
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border-color 0.2s;
        }
        #markdown-content a:hover {
            border-bottom-color: var(--green-2);
        }
        #markdown-content strong {
            font-weight: 600;
            color: var(--text);
        }

        /* Checkbox-Listen */
        #markdown-content input[type="checkbox"] {
            margin-right: 8px;
        }

        /* Print-Styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            header {
                border: none;
                box-shadow: none;
            }
            .btn {
                display: none;
            }
            .content {
                border: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📚 Best Practice - Feedback-Gestaltung</h1>
        </header>

        <div class="content">
            <div id="markdown-content"></div>
        </div>
    </div>

    <script>
        // Markdown-Inhalt
        const markdownContent = <?php echo json_encode($markdownContent, JSON_UNESCAPED_UNICODE); ?>;

        // marked.js konfigurieren
        marked.setOptions({
            breaks: true,
            gfm: true
        });

        // Markdown zu HTML konvertieren und einfügen
        document.getElementById('markdown-content').innerHTML = marked.parse(markdownContent);
    </script>
</body>
</html>
