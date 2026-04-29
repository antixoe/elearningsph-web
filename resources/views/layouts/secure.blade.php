<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Exam Browser') }}</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #07111d;
            --bg-alt: #0c1728;
            --panel: rgba(12, 20, 35, 0.84);
            --panel-solid: #0f1b30;
            --panel-border: rgba(255, 176, 106, 0.16);
            --text: #eaf1ff;
            --muted: #d6c2b1;
            --soft: #f4e4d6;
            --accent: #fdba74;
            --accent-strong: #fb923c;
            --accent-warm: #f59e0b;
            --danger: #fb7185;
            --danger-strong: #e11d48;
            --success: #34d399;
            --shadow: 0 24px 80px rgba(0, 0, 0, 0.42);
            --radius-xl: 28px;
            --radius-lg: 20px;
            --radius-md: 14px;
        }

        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(249, 115, 22, 0.18), transparent 28%),
                radial-gradient(circle at top right, rgba(251, 146, 60, 0.16), transparent 22%),
                linear-gradient(160deg, #120804 0%, #1b1207 52%, #0d0905 100%);
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(to bottom, rgba(0,0,0,0.7), transparent 90%);
            opacity: 0.28;
        }

        a { color: inherit; text-decoration: none; }
        button, input { font: inherit; }

        .page {
            position: relative;
            width: min(1200px, calc(100vw - 32px));
            margin: 0 auto;
            padding: 28px 0 40px;
        }

        .shell {
            position: relative;
            background: linear-gradient(180deg, rgba(35, 20, 11, 0.94), rgba(16, 11, 8, 0.9));
            border: 1px solid var(--panel-border);
            border-radius: 34px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(22px);
            overflow: hidden;
        }

        .shell::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(130deg, rgba(251, 146, 60, 0.12), transparent 34%),
                linear-gradient(310deg, rgba(245, 158, 11, 0.1), transparent 24%);
            pointer-events: none;
        }

        .pad { padding: 28px; position: relative; z-index: 1; }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 9px 14px;
            border-radius: 999px;
            background: rgba(251, 146, 60, 0.12);
            border: 1px solid rgba(251, 146, 60, 0.24);
            color: var(--accent);
            font-size: 12px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .grid {
            display: grid;
            gap: 20px;
        }

        .grid-2 {
            grid-template-columns: 1.1fr 0.9fr;
        }

        .grid-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .card {
            background: linear-gradient(180deg, rgba(34, 20, 12, 0.96), rgba(19, 12, 9, 0.94));
            border: 1px solid var(--panel-border);
            border-radius: var(--radius-xl);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.24);
        }

        .card-inner { padding: 22px; }

        .title {
            margin: 14px 0 12px;
            font-size: clamp(2.3rem, 6vw, 5rem);
            line-height: 0.95;
            letter-spacing: -0.06em;
        }

        .lead {
            margin: 0;
            max-width: 62ch;
            color: var(--soft);
            font-size: 1rem;
            line-height: 1.7;
        }

        .muted { color: var(--muted); }
        .tight { line-height: 1.35; }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
            align-items: center;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 48px;
            padding: 0 18px;
            border: 1px solid transparent;
            border-radius: 14px;
            cursor: pointer;
            transition: transform 120ms ease, border-color 120ms ease, background 120ms ease, opacity 120ms ease;
            font-weight: 700;
        }

        .button:hover { transform: translateY(-1px); }
        .button:active { transform: translateY(0); }
        .button-primary {
            color: #04131b;
            background: linear-gradient(135deg, #fdba74, #f97316);
        }
        .button-secondary {
            color: var(--text);
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.08);
        }
        .button-danger {
            color: #fff;
            background: linear-gradient(135deg, #f97316, #ef4444);
        }

        .field {
            display: grid;
            gap: 8px;
            margin-bottom: 16px;
        }

        .label {
            color: var(--soft);
            font-size: 0.92rem;
            font-weight: 600;
        }

        .input, .textarea {
            width: 100%;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 16px;
            background: rgba(2, 8, 23, 0.42);
            color: var(--text);
            padding: 14px 16px;
            outline: none;
            transition: border-color 120ms ease, box-shadow 120ms ease, transform 120ms ease;
        }

        .input:focus, .textarea:focus {
            border-color: rgba(251, 146, 60, 0.72);
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.18);
        }

        .input::placeholder, .textarea::placeholder { color: rgba(158, 176, 207, 0.55); }

        .stat {
            display: grid;
            gap: 6px;
            padding: 16px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.035);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .stat-value {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .stat-label {
            color: var(--muted);
            font-size: 0.88rem;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.06);
            color: var(--soft);
            border: 1px solid rgba(255, 255, 255, 0.07);
            font-size: 0.9rem;
        }

        .pill-success {
            background: rgba(52, 211, 153, 0.12);
            color: #9ef0c7;
            border-color: rgba(52, 211, 153, 0.18);
        }

        .pill-danger {
            background: rgba(251, 113, 133, 0.12);
            color: #fecdd3;
            border-color: rgba(251, 113, 133, 0.18);
        }

        .callout {
            padding: 18px;
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(251, 146, 60, 0.1), rgba(251, 146, 60, 0.04));
            border: 1px solid rgba(251, 146, 60, 0.2);
            color: var(--soft);
        }

        .list {
            display: grid;
            gap: 12px;
            margin: 18px 0 0;
            padding: 0;
        }

        .list li {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            color: var(--soft);
            line-height: 1.55;
        }

        .dot {
            width: 10px;
            height: 10px;
            margin-top: 7px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fdba74, #f59e0b);
            flex: none;
        }

        .chrome {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(7, 13, 24, 0.92);
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .chrome-left, .chrome-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .traffic {
            display: inline-flex;
            gap: 7px;
            margin-right: 6px;
        }

        .traffic i {
            width: 11px;
            height: 11px;
            border-radius: 50%;
            display: block;
        }

        .traffic i:nth-child(1) { background: #fb7185; }
        .traffic i:nth-child(2) { background: #f59e0b; }
        .traffic i:nth-child(3) { background: #34d399; }

        .frame-wrap {
            position: relative;
            background: #120804;
            min-height: 70vh;
        }

        .frame {
            width: 100%;
            min-height: 70vh;
            border: 0;
            background: #fff;
        }

        .overlay {
            position: absolute;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(2, 6, 23, 0.82);
            backdrop-filter: blur(12px);
            z-index: 10;
        }

        .overlay.show { display: flex; }

        .overlay-card {
            width: min(560px, 100%);
            border-radius: 28px;
            padding: 28px;
            background: linear-gradient(180deg, rgba(39, 22, 12, 0.98), rgba(18, 11, 7, 0.97));
            border: 1px solid rgba(251, 113, 133, 0.22);
            box-shadow: var(--shadow);
        }

        .stack { display: grid; gap: 14px; }

        .helper {
            font-size: 0.92rem;
            color: var(--muted);
            line-height: 1.55;
        }

        .hidden { display: none !important; }

        @media (max-width: 960px) {
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
            .page { width: min(100vw - 20px, 1200px); padding-top: 14px; }
            .pad { padding: 20px; }
            .chrome { align-items: flex-start; flex-direction: column; }
            .frame, .frame-wrap { min-height: 58vh; }
        }
    </style>
</head>
<body>
    <main class="page">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
