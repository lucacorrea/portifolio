<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#061B28">
  <title>Dashboard Premium | K.Yamaguchi Service</title>
  <style>
    :root {
      --bg: #eef3f6;
      --bg-2: #f8fbfc;
      --panel: #ffffff;
      --panel-soft: #f5f8fa;
      --ink: #0f172a;
      --muted: #64748b;
      --muted-2: #94a3b8;
      --line: #dbe5eb;
      --line-2: #edf2f5;
      --primary: #0f766e;
      --primary-2: #0b5f59;
      --primary-3: #14b8a6;
      --navy: #061b28;
      --navy-2: #092a3d;
      --blue: #2563eb;
      --green: #15803d;
      --amber: #b45309;
      --red: #b91c1c;
      --purple: #6d28d9;
      --shadow-sm: 0 8px 20px rgba(15, 23, 42, .06);
      --shadow-md: 0 18px 45px rgba(15, 23, 42, .10);
      --shadow-lg: 0 30px 70px rgba(2, 12, 27, .18);
      --radius: 16px;
      --radius-sm: 10px;
      --sidebar: 286px;
      --topbar: 74px;
    }

    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      margin: 0;
      min-height: 100vh;
      color: var(--ink);
      background:
        radial-gradient(circle at top left, rgba(20,184,166,.16), transparent 34rem),
        linear-gradient(135deg, #edf4f7 0%, #f8fafc 45%, #eef6f5 100%);
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      -webkit-font-smoothing: antialiased;
      text-rendering: geometricPrecision;
    }

    a { color: inherit; text-decoration: none; }
    button, input { font: inherit; }
    button { border: 0; cursor: pointer; }
    .app-shell { min-height: 100vh; }

    .sidebar {
      position: fixed;
      inset: 0 auto 0 0;
      z-index: 50;
      width: var(--sidebar);
      display: flex;
      flex-direction: column;
      color: #d7e3eb;
      background:
        linear-gradient(180deg, rgba(6,27,40,.98), rgba(5,21,32,.98)),
        radial-gradient(circle at 10% 0%, rgba(20,184,166,.30), transparent 19rem);
      border-right: 1px solid rgba(255,255,255,.08);
      box-shadow: var(--shadow-lg);
      transition: transform .25s ease;
    }

    .sidebar::before {
      content: "";
      position: absolute;
      inset: 0;
      pointer-events: none;
      opacity: .24;
      background-image:
        linear-gradient(rgba(255,255,255,.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.06) 1px, transparent 1px);
      background-size: 36px 36px;
      mask-image: linear-gradient(to bottom, black, transparent 85%);
    }

    .brand-box,
    .side-nav,
    .sidebar-footer,
    .quick-card { position: relative; z-index: 1; }

    .brand-box {
      padding: 24px 22px 20px;
      border-bottom: 1px solid rgba(255,255,255,.08);
    }

    .brand-row { display: flex; align-items: center; gap: 12px; }
    .brand-logo {
      width: 48px;
      height: 48px;
      display: grid;
      place-items: center;
      flex: 0 0 auto;
      color: white;
      font-weight: 900;
      letter-spacing: -.04em;
      border-radius: 14px;
      background:
        linear-gradient(135deg, rgba(20,184,166,1), rgba(37,99,235,.95)),
        #0f766e;
      box-shadow: 0 16px 34px rgba(20,184,166,.28);
    }

    .brand-name { display: block; color: white; font-size: 15px; font-weight: 850; line-height: 1.1; }
    .brand-sub { display: block; margin-top: 4px; color: #8fb1bf; font-size: 12px; font-weight: 600; }
    .brand-status {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-top: 18px;
      padding: 8px 10px;
      color: #b8f7ed;
      font-size: 12px;
      font-weight: 750;
      background: rgba(20,184,166,.12);
      border: 1px solid rgba(20,184,166,.24);
      border-radius: 999px;
    }
    .brand-status::before {
      content: "";
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #5eead4;
      box-shadow: 0 0 0 4px rgba(94,234,212,.12);
    }

    .side-nav { padding: 16px 12px; flex: 1; overflow-y: auto; }
    .nav-label {
      margin: 14px 12px 8px;
      color: #7093a2;
      font-size: 10px;
      font-weight: 850;
      letter-spacing: .12em;
      text-transform: uppercase;
    }
    .nav-item {
      position: relative;
      display: flex;
      align-items: center;
      gap: 12px;
      min-height: 46px;
      padding: 11px 13px;
      margin-bottom: 5px;
      color: #bad0db;
      border-radius: 13px;
      border: 1px solid transparent;
      transition: background .18s ease, border-color .18s ease, color .18s ease, transform .18s ease;
    }
    .nav-item:hover {
      color: #fff;
      background: rgba(255,255,255,.065);
      border-color: rgba(255,255,255,.08);
      transform: translateX(2px);
    }
    .nav-item.active {
      color: #fff;
      background: linear-gradient(135deg, rgba(20,184,166,.20), rgba(37,99,235,.13));
      border-color: rgba(20,184,166,.26);
      box-shadow: inset 3px 0 0 #14b8a6;
    }
    .nav-icon {
      width: 28px;
      height: 28px;
      display: grid;
      place-items: center;
      flex: 0 0 auto;
      border-radius: 9px;
      color: #ccfbf1;
      background: rgba(255,255,255,.075);
      font-size: 14px;
    }
    .nav-text { font-size: 13px; font-weight: 760; }

    .quick-card {
      margin: 0 14px 14px;
      padding: 16px;
      border-radius: 18px;
      background: linear-gradient(145deg, rgba(255,255,255,.10), rgba(255,255,255,.035));
      border: 1px solid rgba(255,255,255,.10);
    }
    .quick-card span {
      display: block;
      color: #8fb1bf;
      font-size: 10px;
      font-weight: 850;
      letter-spacing: .11em;
      text-transform: uppercase;
    }
    .quick-card strong { display: block; margin-top: 7px; color: white; font-size: 14px; }
    .quick-card p { margin: 7px 0 14px; color: #9db8c5; font-size: 12px; line-height: 1.45; }

    .sidebar-footer {
      display: flex;
      align-items: center;
      gap: 11px;
      padding: 16px 20px;
      border-top: 1px solid rgba(255,255,255,.08);
    }
    .avatar {
      width: 38px;
      height: 38px;
      display: grid;
      place-items: center;
      flex: 0 0 auto;
      color: white;
      font-size: 12px;
      font-weight: 900;
      border-radius: 12px;
      background: rgba(20,184,166,.16);
      border: 1px solid rgba(94,234,212,.18);
    }
    .user-title { display: block; color: white; font-size: 13px; font-weight: 800; }
    .user-role { display: block; color: #8fb1bf; font-size: 11px; margin-top: 2px; }

    .overlay {
      position: fixed;
      inset: 0;
      z-index: 45;
      display: none;
      background: rgba(2,6,23,.48);
      backdrop-filter: blur(4px);
    }
    .overlay.show { display: block; }

    .main {
      min-height: 100vh;
      margin-left: var(--sidebar);
      padding: 22px;
    }

    .topbar {
      position: sticky;
      top: 0;
      z-index: 30;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      min-height: var(--topbar);
      padding: 12px 14px 12px 18px;
      background: rgba(255,255,255,.82);
      border: 1px solid rgba(219,229,235,.86);
      border-radius: 20px;
      box-shadow: var(--shadow-sm);
      backdrop-filter: blur(14px);
    }

    .top-left,
    .top-actions { display: flex; align-items: center; gap: 12px; }
    .menu-btn {
      display: none;
      width: 42px;
      height: 42px;
      place-items: center;
      color: var(--navy);
      background: var(--panel-soft);
      border: 1px solid var(--line);
      border-radius: 13px;
    }

    .search-box { position: relative; width: min(510px, 48vw); }
    .search-box input {
      width: 100%;
      height: 46px;
      padding: 0 16px 0 44px;
      color: var(--ink);
      background: #f8fbfc;
      border: 1px solid var(--line);
      border-radius: 14px;
      outline: none;
      transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
    }
    .search-box input:focus {
      background: #fff;
      border-color: rgba(20,184,166,.55);
      box-shadow: 0 0 0 4px rgba(20,184,166,.10);
    }
    .search-icon {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted-2);
      pointer-events: none;
    }

    .live-chip,
    .date-chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      min-height: 42px;
      padding: 0 12px;
      color: var(--muted);
      font-size: 12px;
      font-weight: 760;
      background: #f8fbfc;
      border: 1px solid var(--line);
      border-radius: 13px;
      white-space: nowrap;
    }
    .live-chip::before {
      content: "";
      width: 7px;
      height: 7px;
      background: var(--green);
      border-radius: 50%;
      box-shadow: 0 0 0 4px rgba(21,128,61,.12);
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      min-height: 42px;
      padding: 0 15px;
      font-size: 13px;
      font-weight: 850;
      border-radius: 13px;
      border: 1px solid transparent;
      transition: transform .18s ease, box-shadow .18s ease, background .18s ease, border-color .18s ease;
      white-space: nowrap;
    }
    .btn:hover { transform: translateY(-1px); }
    .btn-primary {
      color: white;
      background: linear-gradient(135deg, var(--primary), #0f9f94);
      box-shadow: 0 14px 28px rgba(15,118,110,.20);
    }
    .btn-primary:hover { background: linear-gradient(135deg, var(--primary-2), var(--primary)); }
    .btn-ghost {
      color: var(--ink);
      background: #fff;
      border-color: var(--line);
    }
    .btn-ghost:hover { box-shadow: var(--shadow-sm); }
    .btn-dark {
      color: white;
      background: var(--navy);
      border-color: rgba(255,255,255,.06);
    }

    .content { padding-top: 20px; }
    .hero {
      position: relative;
      overflow: hidden;
      display: grid;
      grid-template-columns: minmax(0, 1.4fr) minmax(320px, .75fr);
      gap: 18px;
      margin-bottom: 18px;
    }
    .hero-main {
      position: relative;
      overflow: hidden;
      padding: 28px;
      color: white;
      background:
        linear-gradient(135deg, rgba(6,27,40,.98), rgba(9,42,61,.94)),
        radial-gradient(circle at top right, rgba(20,184,166,.45), transparent 22rem);
      border-radius: 24px;
      box-shadow: var(--shadow-md);
      min-height: 240px;
    }
    .hero-main::before {
      content: "";
      position: absolute;
      inset: 0;
      opacity: .20;
      background-image:
        linear-gradient(rgba(255,255,255,.14) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.14) 1px, transparent 1px);
      background-size: 42px 42px;
      mask-image: radial-gradient(circle at 80% 20%, black, transparent 65%);
    }
    .hero-main > * { position: relative; z-index: 1; }
    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 13px;
      color: #b8f7ed;
      font-size: 11px;
      font-weight: 900;
      letter-spacing: .12em;
      text-transform: uppercase;
    }
    .eyebrow::before {
      content: "";
      width: 24px;
      height: 2px;
      background: #5eead4;
      border-radius: 99px;
    }
    h1 { margin: 0; font-size: clamp(27px, 4vw, 44px); line-height: .98; letter-spacing: -.045em; }
    .hero-copy { max-width: 720px; margin: 14px 0 0; color: #bad0db; font-size: 15px; line-height: 1.65; }
    .hero-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 22px; }
    .hero-actions .btn-ghost { color: white; background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.12); }

    .hero-metrics {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      margin-top: 24px;
    }
    .micro-metric {
      padding: 13px;
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.10);
      border-radius: 15px;
    }
    .micro-metric span { display: block; color: #8fb1bf; font-size: 11px; font-weight: 760; }
    .micro-metric strong { display: block; margin-top: 5px; color: #fff; font-size: 18px; letter-spacing: -.02em; }

    .hero-side {
      display: grid;
      gap: 14px;
    }
    .priority-card {
      padding: 22px;
      background: rgba(255,255,255,.92);
      border: 1px solid var(--line);
      border-radius: 24px;
      box-shadow: var(--shadow-sm);
    }
    .priority-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
    .priority-card h2, .panel h2 { margin: 0; font-size: 17px; letter-spacing: -.02em; }
    .priority-card p { margin: 7px 0 0; color: var(--muted); font-size: 13px; line-height: 1.45; }
    .score-ring {
      width: 78px;
      height: 78px;
      display: grid;
      place-items: center;
      flex: 0 0 auto;
      color: var(--navy);
      font-size: 19px;
      font-weight: 900;
      background: conic-gradient(var(--primary-3) 0 76%, #e2e8f0 76% 100%);
      border-radius: 50%;
      position: relative;
    }
    .score-ring::after {
      content: "";
      position: absolute;
      inset: 8px;
      background: white;
      border-radius: 50%;
    }
    .score-ring span { position: relative; z-index: 1; }
    .mini-stack { margin-top: 18px; display: grid; gap: 10px; }
    .mini-row { display: grid; grid-template-columns: 86px 1fr auto; gap: 10px; align-items: center; }
    .mini-row small { color: var(--muted); font-size: 12px; font-weight: 700; }
    .bar-track { height: 9px; overflow: hidden; background: #e5edf1; border-radius: 999px; }
    .bar-fill { width: var(--w); height: 100%; background: linear-gradient(90deg, var(--primary), var(--primary-3)); border-radius: 999px; }
    .mini-row strong { font-size: 12px; }

    .section-grid { display: grid; gap: 18px; }
    .kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .kpi-card {
      position: relative;
      overflow: hidden;
      min-height: 152px;
      padding: 20px;
      background: rgba(255,255,255,.94);
      border: 1px solid var(--line);
      border-radius: 22px;
      box-shadow: var(--shadow-sm);
      transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .kpi-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: rgba(20,184,166,.24); }
    .kpi-card::after {
      content: "";
      position: absolute;
      right: -30px;
      top: -35px;
      width: 110px;
      height: 110px;
      background: radial-gradient(circle, rgba(20,184,166,.13), transparent 70%);
      border-radius: 50%;
    }
    .kpi-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .kpi-icon {
      width: 44px;
      height: 44px;
      display: grid;
      place-items: center;
      color: var(--navy);
      font-weight: 900;
      border-radius: 14px;
      background: #e6fffb;
      border: 1px solid rgba(20,184,166,.18);
    }
    .trend {
      padding: 6px 9px;
      color: var(--green);
      font-size: 11px;
      font-weight: 850;
      border-radius: 999px;
      background: #dcfce7;
    }
    .trend.warn { color: var(--amber); background: #fef3c7; }
    .trend.info { color: var(--blue); background: #dbeafe; }
    .kpi-label { display: block; margin-top: 18px; color: var(--muted); font-size: 12px; font-weight: 760; }
    .kpi-value { display: block; margin-top: 5px; color: var(--ink); font-size: 29px; font-weight: 900; letter-spacing: -.045em; }
    .kpi-foot { display: block; margin-top: 5px; color: var(--muted); font-size: 12px; }

    .two-columns { grid-template-columns: minmax(0, 1.12fr) minmax(380px, .88fr); margin-top: 18px; }
    .panel {
      background: rgba(255,255,255,.94);
      border: 1px solid var(--line);
      border-radius: 24px;
      box-shadow: var(--shadow-sm);
    }
    .panel-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 14px;
      padding: 20px 20px 0;
    }
    .panel-kicker { color: var(--muted); font-size: 11px; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; }
    .panel-sub { margin: 6px 0 0; color: var(--muted); font-size: 13px; }
    .panel-body { padding: 20px; }

    .status-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .status-card {
      padding: 16px;
      border: 1px solid var(--line-2);
      border-radius: 18px;
      background: linear-gradient(180deg, #fff, #f8fbfc);
    }
    .status-card strong { display: block; font-size: 22px; letter-spacing: -.04em; }
    .status-card span { color: var(--muted); font-size: 12px; font-weight: 750; }
    .status-card .bar-track { margin-top: 12px; }

    .chart-bars { height: 244px; display: flex; align-items: end; gap: 12px; padding: 18px 8px 0; }
    .chart-col { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; gap: 9px; }
    .chart-bar {
      width: 100%;
      max-width: 46px;
      min-height: 26px;
      height: var(--h);
      border-radius: 12px 12px 5px 5px;
      background: linear-gradient(180deg, rgba(20,184,166,.95), rgba(15,118,110,.95));
      box-shadow: 0 12px 24px rgba(15,118,110,.16);
    }
    .chart-col:nth-child(even) .chart-bar { background: linear-gradient(180deg, rgba(37,99,235,.92), rgba(30,64,175,.92)); }
    .chart-label { color: var(--muted); font-size: 11px; font-weight: 750; }
    .chart-value { color: var(--ink); font-size: 12px; font-weight: 900; }

    .work-grid { grid-template-columns: minmax(0, 1.5fr) minmax(360px, .8fr); margin-top: 18px; align-items: start; }
    .table-wrap { overflow-x: auto; padding: 0 20px 20px; }
    table { width: 100%; min-width: 820px; border-collapse: separate; border-spacing: 0 10px; font-size: 13px; }
    th {
      padding: 0 14px 2px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 900;
      letter-spacing: .08em;
      text-align: left;
      text-transform: uppercase;
    }
    td {
      padding: 14px;
      background: #f8fbfc;
      border-block: 1px solid var(--line-2);
      vertical-align: middle;
    }
    td:first-child { border-left: 1px solid var(--line-2); border-radius: 15px 0 0 15px; }
    td:last-child { border-right: 1px solid var(--line-2); border-radius: 0 15px 15px 0; }
    tbody tr { transition: transform .16s ease, box-shadow .16s ease; }
    tbody tr:hover { transform: translateY(-1px); box-shadow: 0 12px 26px rgba(15,23,42,.06); }
    .os-code { display: block; color: var(--ink); font-weight: 900; }
    .muted { color: var(--muted); font-size: 12px; }
    .client-name { font-weight: 850; }
    .service-name { color: var(--muted); font-weight: 700; }
    .status {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 7px 9px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 900;
      white-space: nowrap;
    }
    .status::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
    .status.running { color: var(--amber); background: #fef3c7; }
    .status.scheduled { color: var(--purple); background: #ede9fe; }
    .status.waiting { color: #c2410c; background: #ffedd5; }
    .status.done { color: var(--green); background: #dcfce7; }
    .status.critical { color: var(--red); background: #fee2e2; }
    .action-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 34px;
      padding: 0 12px;
      color: var(--navy);
      background: white;
      border: 1px solid var(--line);
      border-radius: 10px;
      font-size: 12px;
      font-weight: 900;
    }
    .action-link:hover { border-color: rgba(20,184,166,.40); color: var(--primary); }

    .side-stack { display: grid; gap: 18px; }
    .timeline { display: grid; gap: 12px; }
    .timeline-item {
      display: grid;
      grid-template-columns: 54px 1fr;
      gap: 12px;
      padding: 13px;
      background: #f8fbfc;
      border: 1px solid var(--line-2);
      border-radius: 18px;
    }
    .time {
      display: grid;
      place-items: center;
      height: 40px;
      color: var(--navy);
      font-size: 12px;
      font-weight: 900;
      background: white;
      border: 1px solid var(--line);
      border-radius: 13px;
    }
    .timeline-item strong { display: block; font-size: 13px; }
    .timeline-item span { display: block; margin-top: 3px; color: var(--muted); font-size: 12px; }
    .timeline-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 8px;
      color: var(--muted);
      background: white;
      border: 1px solid var(--line);
      border-radius: 999px;
      font-size: 11px;
      font-weight: 800;
    }
    .alert-list { display: grid; gap: 10px; }
    .alert-item {
      position: relative;
      padding: 14px 14px 14px 42px;
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 17px;
    }
    .alert-item::before {
      content: "!";
      position: absolute;
      left: 13px;
      top: 14px;
      width: 18px;
      height: 18px;
      display: grid;
      place-items: center;
      color: white;
      background: var(--amber);
      border-radius: 7px;
      font-size: 12px;
      font-weight: 900;
    }
    .alert-item.critical::before { background: var(--red); }
    .alert-item.info::before { background: var(--blue); }
    .alert-item strong { display: block; font-size: 13px; }
    .alert-item span { display: block; margin-top: 3px; color: var(--muted); font-size: 12px; line-height: 1.45; }

    .toast {
      position: fixed;
      right: 22px;
      bottom: 22px;
      z-index: 80;
      transform: translateY(16px);
      opacity: 0;
      pointer-events: none;
      padding: 13px 15px;
      color: white;
      background: var(--navy);
      border-radius: 14px;
      box-shadow: var(--shadow-lg);
      font-size: 13px;
      font-weight: 800;
      transition: opacity .2s ease, transform .2s ease;
    }
    .toast.show { transform: translateY(0); opacity: 1; }
    .empty-row { display: none; }

    @media (max-width: 1180px) {
      .hero, .two-columns, .work-grid { grid-template-columns: 1fr; }
      .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .search-box { width: min(460px, 42vw); }
    }

    @media (max-width: 860px) {
      .sidebar { transform: translateX(-102%); }
      .sidebar.open { transform: translateX(0); }
      .main { margin-left: 0; padding: 12px; }
      .menu-btn { display: grid; }
      .topbar { border-radius: 16px; align-items: stretch; flex-direction: column; }
      .top-left, .top-actions { width: 100%; justify-content: space-between; }
      .search-box { flex: 1; width: auto; }
      .date-chip { display: none; }
      .hero-main { padding: 22px; }
      .hero-metrics { grid-template-columns: 1fr; }
      .status-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 620px) {
      .kpis { grid-template-columns: 1fr; }
      .hero-actions, .top-actions { align-items: stretch; flex-direction: column; }
      .btn { width: 100%; }
      .panel-head { flex-direction: column; }
      .quick-hide-sm { display: none; }
      .chart-bars { gap: 7px; }
      .chart-label { writing-mode: vertical-rl; transform: rotate(180deg); }
    }

    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { transition: none !important; scroll-behavior: auto !important; }
    }
  </style>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Menu principal">
      <div class="brand-box">
        <div class="brand-row">
          <div class="brand-logo">KY</div>
          <div>
            <strong class="brand-name">K.Yamaguchi</strong>
            <span class="brand-sub">Service OS Premium</span>
          </div>
        </div>
        <span class="brand-status">Operação online</span>
      </div>

      <nav class="side-nav">
        <div class="nav-label">Operação</div>
        <a href="dashboard.php" class="nav-item active"><span class="nav-icon">▦</span><span class="nav-text">Dashboard</span></a>
        <a href="clientes.php" class="nav-item"><span class="nav-icon">◉</span><span class="nav-text">Clientes</span></a>
        <a href="ordens-servico.php" class="nav-item"><span class="nav-icon">▣</span><span class="nav-text">Ordens de Serviço</span></a>
        <a href="orcamentos.php" class="nav-item"><span class="nav-icon">R$</span><span class="nav-text">Orçamentos</span></a>
        <a href="pecas.php" class="nav-item"><span class="nav-icon">⚙</span><span class="nav-text">Peças e Estoque</span></a>

        <div class="nav-label">Gestão</div>
        <a href="tipos-servico.php" class="nav-item"><span class="nav-icon">✦</span><span class="nav-text">Tipos de Serviço</span></a>
        <a href="relatorios.php" class="nav-item"><span class="nav-icon">⌁</span><span class="nav-text">Relatórios</span></a>
        <a href="notas-fiscais.php" class="nav-item"><span class="nav-icon">NF</span><span class="nav-text">Notas Fiscais</span></a>
        <a href="configuracoes.php" class="nav-item"><span class="nav-icon">●</span><span class="nav-text">Configurações</span></a>
      </nav>

      <div class="quick-card">
        <span>Atalho operacional</span>
        <strong>Nova Ordem de Serviço</strong>
        <p>Abra uma OS com cliente, equipamento, serviço, peças e status técnico sem sair do fluxo.</p>
        <a href="ordens-servico.php?action=new" class="btn btn-primary">+ Nova OS</a>
      </div>

      <div class="sidebar-footer">
        <div class="avatar">OP</div>
        <div>
          <strong class="user-title">Operador</strong>
          <span class="user-role">Administrador técnico</span>
        </div>
      </div>
    </aside>

    <div class="overlay" id="overlay" aria-hidden="true"></div>

    <main class="main">
      <header class="topbar">
        <div class="top-left">
          <button class="menu-btn" id="menuToggle" type="button" aria-label="Abrir menu">☰</button>
          <form class="search-box" role="search" onsubmit="return false;">
            <span class="search-icon">⌕</span>
            <input id="tableSearch" type="search" placeholder="Buscar cliente, OS, técnico, serviço ou status..." autocomplete="off">
          </form>
        </div>
        <div class="top-actions">
          <span class="date-chip" id="currentDate">22/05/2026</span>
          <span class="live-chip">Dados sincronizados</span>
          <button class="btn btn-ghost" id="refreshBtn" type="button">Atualizar</button>
        </div>
      </header>

      <div class="content">
        <section class="hero">
          <div class="hero-main">
            <span class="eyebrow">Central de controle</span>
            <h1>Dashboard operacional para refrigeração com foco em OS, SLA e faturamento.</h1>
            <p class="hero-copy">Uma visão mais profissional para acompanhar serviços em campo, peças críticas, orçamentos pendentes e atendimentos do dia sem depender de telas carregadas ou visual genérico.</p>
            <div class="hero-actions">
              <a href="ordens-servico.php?action=new" class="btn btn-primary">+ Abrir nova OS</a>
              <a href="orcamentos.php?action=new" class="btn btn-ghost">Novo orçamento</a>
              <a href="relatorios.php" class="btn btn-ghost quick-hide-sm">Ver relatórios</a>
            </div>
            <div class="hero-metrics">
              <div class="micro-metric"><span>SLA dentro do prazo</span><strong>96%</strong></div>
              <div class="micro-metric"><span>Tempo médio</span><strong>2h15</strong></div>
              <div class="micro-metric"><span>Conversão orçamento</span><strong>68%</strong></div>
            </div>
          </div>

          <aside class="hero-side">
            <div class="priority-card">
              <div class="priority-top">
                <div>
                  <span class="panel-kicker">Saúde da operação</span>
                  <h2>Eficiência técnica</h2>
                  <p>Índice calculado por OS finalizadas, atrasos, peças críticas e orçamento convertido.</p>
                </div>
                <div class="score-ring"><span>76%</span></div>
              </div>
              <div class="mini-stack">
                <div class="mini-row"><small>OS no prazo</small><div class="bar-track"><div class="bar-fill" style="--w: 86%"></div></div><strong>86%</strong></div>
                <div class="mini-row"><small>Peças OK</small><div class="bar-track"><div class="bar-fill" style="--w: 71%"></div></div><strong>71%</strong></div>
                <div class="mini-row"><small>NF emitida</small><div class="bar-track"><div class="bar-fill" style="--w: 64%"></div></div><strong>64%</strong></div>
              </div>
            </div>
          </aside>
        </section>

        <section class="section-grid kpis" id="dashboardStats">
          <article class="kpi-card">
            <div class="kpi-head"><div class="kpi-icon">OS</div><span class="trend info">+8 semana</span></div>
            <span class="kpi-label">OS abertas</span>
            <strong class="kpi-value" data-counter="24">24</strong>
            <span class="kpi-foot">Chamados ativos em atendimento ou triagem.</span>
          </article>
          <article class="kpi-card">
            <div class="kpi-head"><div class="kpi-icon">EX</div><span class="trend warn">5 em rota</span></div>
            <span class="kpi-label">Em execução</span>
            <strong class="kpi-value" data-counter="13">13</strong>
            <span class="kpi-foot">Serviços com técnico definido para hoje.</span>
          </article>
          <article class="kpi-card">
            <div class="kpi-head"><div class="kpi-icon">OR</div><span class="trend warn">R$ 12.480</span></div>
            <span class="kpi-label">Orçamentos pendentes</span>
            <strong class="kpi-value" data-counter="18">18</strong>
            <span class="kpi-foot">Propostas aguardando aprovação do cliente.</span>
          </article>
          <article class="kpi-card">
            <div class="kpi-head"><div class="kpi-icon">R$</div><span class="trend">+12%</span></div>
            <span class="kpi-label">Faturamento do mês</span>
            <strong class="kpi-value">R$ 38.920</strong>
            <span class="kpi-foot">Receita prevista com serviços finalizados.</span>
          </article>
        </section>

        <section class="section-grid two-columns">
          <article class="panel">
            <div class="panel-head">
              <div>
                <span class="panel-kicker">Status das OS</span>
                <h2>Distribuição operacional</h2>
                <p class="panel-sub">Resumo visual para identificar gargalos antes que virem atraso.</p>
              </div>
              <a href="ordens-servico.php" class="btn btn-ghost">Ver OS</a>
            </div>
            <div class="panel-body status-grid">
              <div class="status-card"><strong>13</strong><span>Em andamento</span><div class="bar-track"><div class="bar-fill" style="--w: 72%"></div></div></div>
              <div class="status-card"><strong>7</strong><span>Agendadas</span><div class="bar-track"><div class="bar-fill" style="--w: 42%"></div></div></div>
              <div class="status-card"><strong>4</strong><span>Aguardando peça</span><div class="bar-track"><div class="bar-fill" style="--w: 26%"></div></div></div>
              <div class="status-card"><strong>19</strong><span>Finalizadas no mês</span><div class="bar-track"><div class="bar-fill" style="--w: 88%"></div></div></div>
            </div>
          </article>

          <article class="panel">
            <div class="panel-head">
              <div>
                <span class="panel-kicker">Financeiro</span>
                <h2>Faturamento mensal</h2>
                <p class="panel-sub">Projeção visual sem biblioteca externa.</p>
              </div>
              <a href="relatorios.php" class="btn btn-ghost">Relatório</a>
            </div>
            <div class="panel-body">
              <div class="chart-bars" aria-label="Faturamento mensal por mês">
                <div class="chart-col"><span class="chart-value">22k</span><div class="chart-bar" style="--h: 42%"></div><span class="chart-label">Jan</span></div>
                <div class="chart-col"><span class="chart-value">26k</span><div class="chart-bar" style="--h: 52%"></div><span class="chart-label">Fev</span></div>
                <div class="chart-col"><span class="chart-value">31k</span><div class="chart-bar" style="--h: 65%"></div><span class="chart-label">Mar</span></div>
                <div class="chart-col"><span class="chart-value">28k</span><div class="chart-bar" style="--h: 58%"></div><span class="chart-label">Abr</span></div>
                <div class="chart-col"><span class="chart-value">34k</span><div class="chart-bar" style="--h: 74%"></div><span class="chart-label">Mai</span></div>
                <div class="chart-col"><span class="chart-value">39k</span><div class="chart-bar" style="--h: 88%"></div><span class="chart-label">Atual</span></div>
              </div>
            </div>
          </article>
        </section>

        <section class="section-grid work-grid">
          <article class="panel">
            <div class="panel-head">
              <div>
                <span class="panel-kicker">Operação recente</span>
                <h2>Últimas ordens de serviço</h2>
                <p class="panel-sub">Use a busca superior para filtrar a tabela sem recarregar a página.</p>
              </div>
              <a href="ordens-servico.php" class="btn btn-ghost">Abrir lista</a>
            </div>
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>OS</th>
                    <th>Cliente</th>
                    <th>Serviço</th>
                    <th>Status</th>
                    <th>Técnico</th>
                    <th>Valor</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody id="recentOrders">
                  <tr data-row="OS-000123 Mercado São José Manutenção Split Em andamento Carlos R$ 280,00">
                    <td><span class="os-code">OS-000123</span><span class="muted">22/05/2026</span></td>
                    <td><span class="client-name">Mercado São José</span></td>
                    <td><span class="service-name">Manutenção Split</span></td>
                    <td><span class="status running">Em andamento</span></td>
                    <td>Carlos</td>
                    <td><strong>R$ 280,00</strong></td>
                    <td><a class="action-link" href="ordens-servico.php?id=123">Ver</a></td>
                  </tr>
                  <tr data-row="OS-000124 João Almeida Higienização Agendada Paulo R$ 150,00">
                    <td><span class="os-code">OS-000124</span><span class="muted">23/05/2026</span></td>
                    <td><span class="client-name">João Almeida</span></td>
                    <td><span class="service-name">Higienização</span></td>
                    <td><span class="status scheduled">Agendada</span></td>
                    <td>Paulo</td>
                    <td><strong>R$ 150,00</strong></td>
                    <td><a class="action-link" href="ordens-servico.php?id=124">Ver</a></td>
                  </tr>
                  <tr data-row="OS-000125 Clínica Vida Norte Troca de peça Aguardando peça Rafael R$ 690,00">
                    <td><span class="os-code">OS-000125</span><span class="muted">24/05/2026</span></td>
                    <td><span class="client-name">Clínica Vida Norte</span></td>
                    <td><span class="service-name">Troca de peça</span></td>
                    <td><span class="status waiting">Aguardando peça</span></td>
                    <td>Rafael</td>
                    <td><strong>R$ 690,00</strong></td>
                    <td><a class="action-link" href="ordens-servico.php?id=125">Ver</a></td>
                  </tr>
                  <tr data-row="OS-000126 Padaria Modelo Câmara fria Finalizada Marcos R$ 1.250,00">
                    <td><span class="os-code">OS-000126</span><span class="muted">21/05/2026</span></td>
                    <td><span class="client-name">Padaria Modelo</span></td>
                    <td><span class="service-name">Câmara fria</span></td>
                    <td><span class="status done">Finalizada</span></td>
                    <td>Marcos</td>
                    <td><strong>R$ 1.250,00</strong></td>
                    <td><a class="action-link" href="ordens-servico.php?id=126">Ver</a></td>
                  </tr>
                  <tr data-row="OS-000127 Supermercado Norte Câmara frigorífica Urgente Amanda R$ 1.890,00">
                    <td><span class="os-code">OS-000127</span><span class="muted">22/05/2026</span></td>
                    <td><span class="client-name">Supermercado Norte</span></td>
                    <td><span class="service-name">Câmara frigorífica</span></td>
                    <td><span class="status critical">Urgente</span></td>
                    <td>Amanda</td>
                    <td><strong>R$ 1.890,00</strong></td>
                    <td><a class="action-link" href="ordens-servico.php?id=127">Ver</a></td>
                  </tr>
                  <tr class="empty-row" id="emptyRow">
                    <td colspan="7"><strong>Nenhum resultado encontrado.</strong> <span class="muted">Revise a busca e tente novamente.</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </article>

          <aside class="side-stack">
            <article class="panel">
              <div class="panel-head">
                <div>
                  <span class="panel-kicker">Agenda</span>
                  <h2>Atendimentos do dia</h2>
                  <p class="panel-sub">Fila compacta para acompanhamento rápido.</p>
                </div>
              </div>
              <div class="panel-body timeline">
                <div class="timeline-item">
                  <div class="time">09:30</div>
                  <div>
                    <strong>Manutenção preventiva</strong>
                    <span>Mercado São José</span>
                    <div class="timeline-meta"><span class="pill">Carlos</span><span class="pill">Zona Norte</span></div>
                  </div>
                </div>
                <div class="timeline-item">
                  <div class="time">11:00</div>
                  <div>
                    <strong>Higienização Split</strong>
                    <span>João Almeida</span>
                    <div class="timeline-meta"><span class="pill">Paulo</span><span class="pill">Residencial</span></div>
                  </div>
                </div>
                <div class="timeline-item">
                  <div class="time">14:30</div>
                  <div>
                    <strong>Troca de peça</strong>
                    <span>Clínica Vida Norte</span>
                    <div class="timeline-meta"><span class="pill">Rafael</span><span class="pill">Aguardando peça</span></div>
                  </div>
                </div>
              </div>
            </article>

            <article class="panel">
              <div class="panel-head">
                <div>
                  <span class="panel-kicker">Atenção</span>
                  <h2>Alertas importantes</h2>
                </div>
              </div>
              <div class="panel-body alert-list">
                <div class="alert-item critical"><strong>Peças com estoque baixo</strong><span>Capacitor 35µF abaixo do mínimo definido. Ideal repor antes das próximas OS.</span></div>
                <div class="alert-item"><strong>Orçamentos próximos de expirar</strong><span>2 propostas vencem nos próximos 3 dias. Envie lembrete por WhatsApp.</span></div>
                <div class="alert-item info"><strong>Notas pendentes</strong><span>Existem 3 documentos fiscais aguardando emissão ou conferência.</span></div>
              </div>
            </article>
          </aside>
        </section>
      </div>
    </main>
  </div>

  <div class="toast" id="toast" role="status" aria-live="polite">Dashboard atualizado com sucesso.</div>

  <script>
    (function () {
      'use strict';

      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('overlay');
      const menuToggle = document.getElementById('menuToggle');
      const refreshBtn = document.getElementById('refreshBtn');
      const toast = document.getElementById('toast');
      const searchInput = document.getElementById('tableSearch');
      const rows = Array.from(document.querySelectorAll('#recentOrders tr[data-row]'));
      const emptyRow = document.getElementById('emptyRow');
      const dateEl = document.getElementById('currentDate');

      const today = new Date();
      dateEl.textContent = today.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
      });

      function openMenu() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden', 'false');
      }

      function closeMenu() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        overlay.setAttribute('aria-hidden', 'true');
      }

      function showToast(message) {
        toast.textContent = message;
        toast.classList.add('show');
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(function () {
          toast.classList.remove('show');
        }, 2300);
      }

      function normalize(value) {
        return String(value || '')
          .toLowerCase()
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '');
      }

      function filterRows() {
        const query = normalize(searchInput.value);
        let visible = 0;

        rows.forEach(function (row) {
          const content = normalize(row.dataset.row + ' ' + row.textContent);
          const match = !query || content.includes(query);
          row.style.display = match ? '' : 'none';
          if (match) visible += 1;
        });

        emptyRow.style.display = visible ? 'none' : 'table-row';
      }

      function animateCounters() {
        const counters = document.querySelectorAll('[data-counter]');
        counters.forEach(function (counter) {
          const target = Number(counter.dataset.counter || 0);
          const duration = 700;
          const start = performance.now();

          function frame(now) {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            counter.textContent = Math.round(target * eased).toLocaleString('pt-BR');
            if (progress < 1) requestAnimationFrame(frame);
          }

          requestAnimationFrame(frame);
        });
      }

      menuToggle.addEventListener('click', openMenu);
      overlay.addEventListener('click', closeMenu);
      searchInput.addEventListener('input', filterRows);

      refreshBtn.addEventListener('click', function () {
        refreshBtn.textContent = 'Sincronizando...';
        refreshBtn.disabled = true;

        window.setTimeout(function () {
          refreshBtn.textContent = 'Atualizar';
          refreshBtn.disabled = false;
          showToast('Dados sincronizados agora.');
        }, 650);
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeMenu();
      });

      animateCounters();
    })();
  </script>
</body>
</html>
