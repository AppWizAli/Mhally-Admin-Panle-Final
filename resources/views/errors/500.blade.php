<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Server Error | Mhally Admin</title>
    <style>
        :root {
            color-scheme: light;
            --background: #f7f8fb;
            --panel: #ffffff;
            --border: #d9dee8;
            --text: #172033;
            --muted: #5e6a7d;
            --accent: #147a64;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: var(--background);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }

        main {
            width: min(100%, 560px);
            padding: 32px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--panel);
            box-shadow: 0 18px 45px rgba(23, 32, 51, 0.08);
        }

        h1 {
            margin: 0 0 12px;
            font-size: 28px;
            line-height: 1.2;
        }

        p {
            margin: 0 0 16px;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.6;
        }

        code {
            display: block;
            overflow-wrap: anywhere;
            padding: 12px;
            border: 1px solid rgba(20, 122, 100, 0.24);
            border-radius: 6px;
            background: rgba(20, 122, 100, 0.08);
            color: var(--accent);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <main>
        <h1>Server-side issue in Mhally Admin</h1>
        <p>The request reached the admin panel, but Laravel hit an internal error. The full details were saved in the server log with this reference ID.</p>
        <code>{{ $errorId ?? 'Check storage/logs/laravel-YYYY-MM-DD.log' }}</code>
    </main>
</body>
</html>
