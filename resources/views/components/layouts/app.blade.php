<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'HookRelay' }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f6f8fb;
            --panel: #ffffff;
            --text: #172033;
            --muted: #65738a;
            --line: #d9e0ea;
            --accent: #176b87;
            --accent-strong: #0f4c5c;
            --danger: #b42318;
            --success: #087443;
            --warning: #936316;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        a { color: inherit; text-decoration: none; }
        .shell { min-height: 100vh; }
        .topbar {
            border-bottom: 1px solid var(--line);
            background: var(--panel);
        }
        .topbar-inner, .content {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
        }
        .topbar-inner {
            min-height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .brand { font-weight: 800; font-size: 18px; }
        .nav { display: flex; gap: 14px; color: var(--muted); font-size: 14px; }
        .content { padding: 28px 0 48px; }
        .page-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }
        h1 { margin: 0; font-size: 30px; line-height: 1.15; letter-spacing: 0; }
        .subtitle { margin: 8px 0 0; color: var(--muted); }
        .stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat, .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
        }
        .stat { padding: 16px; }
        .stat span { display: block; color: var(--muted); font-size: 13px; }
        .stat strong { display: block; margin-top: 8px; font-size: 26px; }
        .panel { overflow: hidden; }
        .toolbar {
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }
        .filters { display: flex; flex-wrap: wrap; gap: 8px; }
        .chip {
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 7px 11px;
            font-size: 13px;
            color: var(--muted);
            background: #fff;
        }
        .chip.active {
            color: #fff;
            border-color: var(--accent);
            background: var(--accent);
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px 16px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        th { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: 0; }
        td { font-size: 14px; }
        tr:last-child td { border-bottom: 0; }
        .status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 12px;
            font-weight: 700;
            background: #eef3f7;
            color: var(--muted);
        }
        .status.processed, .status.succeeded, .status.skipped { background: #e7f6ee; color: var(--success); }
        .status.failed, .status.rejected { background: #fdecec; color: var(--danger); }
        .status.processing, .status.received, .status.pending, .status.queued, .status.retrying { background: #fff4d6; color: var(--warning); }
        .muted { color: var(--muted); }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .section { padding: 18px; }
        .section h2 { margin: 0 0 12px; font-size: 17px; }
        pre {
            margin: 0;
            overflow: auto;
            padding: 14px;
            border-radius: 8px;
            background: #101828;
            color: #e7edf6;
            line-height: 1.5;
            font-size: 13px;
        }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--text);
            font-size: 14px;
            font-weight: 700;
        }
        .button.primary {
            border-color: var(--accent);
            background: var(--accent);
            color: #fff;
        }
        .form-grid {
            display: grid;
            gap: 16px;
        }
        .field label {
            display: block;
            margin-bottom: 7px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }
        .field input {
            width: 100%;
            min-height: 42px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 0 12px;
            color: var(--text);
            background: #fff;
            font: inherit;
        }
        .field input[type="checkbox"] {
            width: 16px;
            min-height: 16px;
            padding: 0;
        }
        .field-error {
            margin-top: 6px;
            color: var(--danger);
            font-size: 13px;
        }
        .inline-field {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .empty { padding: 32px 16px; text-align: center; color: var(--muted); }
        .pagination { padding: 14px 16px; border-top: 1px solid var(--line); }
        .notice {
            margin-bottom: 18px;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 14px;
            font-weight: 700;
            border: 1px solid var(--line);
            background: #fff;
        }
        .notice.success {
            border-color: #b7e4ca;
            background: #ecfdf3;
            color: var(--success);
        }
        .notice.error {
            border-color: #f5c2c0;
            background: #fff1f1;
            color: var(--danger);
        }

        @media (max-width: 820px) {
            .page-head { align-items: flex-start; flex-direction: column; }
            .stats, .grid { grid-template-columns: 1fr; }
            .topbar-inner { align-items: flex-start; flex-direction: column; padding: 14px 0; }
            table { min-width: 760px; }
            .panel.table-wrap { overflow-x: auto; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <div class="topbar-inner">
                <a class="brand" href="{{ route('events.index') }}">HookRelay</a>
                <nav class="nav">
                    <a href="{{ route('events.index') }}">Eventos</a>
                    <a href="{{ route('sources.index') }}">Fontes</a>
                    <a href="{{ url('/up') }}">Health</a>
                </nav>
            </div>
        </header>

        <main class="content">
            @if (session('status'))
                <div class="notice success">{{ session('status') }}</div>
            @endif

            @if (session('error'))
                <div class="notice error">{{ session('error') }}</div>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>
