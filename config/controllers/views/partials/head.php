<?php
/**
 * Partial: head.php
 * Outputs: <!DOCTYPE html>, <html>, <head> ... </head>
 *
 * Expected variables set by the including page:
 *   $pageTitle  (string) – browser title
 *   $extraCss   (string, optional) – additional <style> or <link> tags
 */
$pageTitle = $pageTitle ?? 'SBVS Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> – SBVS Portal</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

    <!-- Page-specific extra CSS (injected by including page via $extraCss) -->
    <?= $extraCss ?? '' ?>

    <style>
        /* ── CSS Design Tokens ──────────────────────────────────────────── */
        :root {
            --font-base:      'Inter', system-ui, -apple-system, sans-serif;
            --sidebar-w:      260px;
            --sidebar-bg:     #1e1e2d;
            --sidebar-hover:  rgba(255,255,255,0.05);
            --sidebar-active: #474761;
            --sidebar-text:   #9899ac;
            --sidebar-text-active: #ffffff;
            --accent:         #6366f1;
            --accent-hover:   #4f46e5;
            --accent-light:   rgba(99,102,241,0.1);
            --bg:             #f4f7f6;
            --surface:        #ffffff;
            --border:         #e4e6ef;
            --text:           #3f4254;
            --text-muted:     #a1a5b7;
            --card-radius:    12px;
            --card-shadow:    0 0 20px 0 rgba(76,87,125,0.02);
            --card-shadow-hover: 0 0 30px 0 rgba(76,87,125,0.06);
        }

        /* ── Base ────────────────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: var(--font-base);
            background: var(--bg);
            color: var(--text);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Layout ──────────────────────────────────────────────────────── */
        .sbvs-layout {
            display: flex;
            min-height: 100vh;
        }

        .sbvs-main {
            flex: 1;
            padding: 28px 28px 40px;
            margin-left: var(--sidebar-w);
            transition: margin-left 0.3s ease;
            min-width: 0;
        }

        @media (max-width: 991.98px) {
            .sbvs-main {
                margin-left: 0;
                padding: 15px 15px 30px;
            }
        }

        /* ── Sidebar ─────────────────────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            background: linear-gradient(180deg, var(--sidebar-bg) 0%, #151521 100%);
            position: fixed;
            top: 0; left: 0;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1050;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, width 0.3s ease;
        }

        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
        }

        /* ── Sidebar: Brand ──────────────────────────────────────────────── */
        .sidebar-brand {
            padding: 22px 20px 18px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .sidebar-brand-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--accent), #8b5cf6);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(99,102,241,0.3);
        }

        .sidebar-brand-icon i { font-size: 1.1rem; color: #fff; }

        .sidebar-brand-text {
            line-height: 1.2;
        }

        .sidebar-brand-name {
            font-size: 0.9rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.02em;
        }

        .sidebar-brand-sub {
            font-size: 0.65rem;
            color: var(--sidebar-text);
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        /* ── Sidebar: User info strip ────────────────────────────────────── */
        .sidebar-user {
            padding: 14px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-user-avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: rgba(99,102,241,0.2);
            color: #a5b4fc;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.85rem;
            flex-shrink: 0;
            border: 1px solid rgba(99,102,241,0.3);
        }

        .sidebar-user-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-user-role {
            font-size: 0.65rem;
            color: var(--sidebar-text);
            white-space: nowrap;
        }

        /* ── Sidebar: Nav ────────────────────────────────────────────────── */
        .sidebar-nav {
            flex: 1;
            padding: 12px 0;
        }

        .sidebar-section-label {
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--sidebar-text);
            padding: 14px 20px 6px;
            opacity: 0.6;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            border-radius: 0;
            transition: background 0.2s, color 0.2s;
            border-left: 3px solid transparent;
            position: relative;
        }

        .sidebar-link:hover {
            background: var(--sidebar-hover);
            color: #fff;
        }

        .sidebar-link.active {
            background: var(--sidebar-active);
            color: var(--sidebar-text-active);
            border-left-color: var(--accent);
            font-weight: 600;
        }

        .sidebar-link i {
            width: 20px;
            font-size: 1rem;
            flex-shrink: 0;
            text-align: center;
        }

        .sidebar-badge {
            margin-left: auto;
            font-size: 0.6rem;
            padding: 2px 7px;
            border-radius: 10px;
            font-weight: 700;
            background: rgba(99,102,241,0.2);
            color: #a5b4fc;
        }

        /* ── Sidebar: Footer ─────────────────────────────────────────────── */
        .sidebar-footer {
            padding: 14px 20px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .sidebar-logout {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            color: #f87171;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 8px;
            transition: background 0.2s;
            border: 1px solid rgba(248,113,113,0.2);
        }

        .sidebar-logout:hover {
            background: rgba(248,113,113,0.1);
            color: #fca5a5;
        }

        /* ── Mobile navbar ───────────────────────────────────────────────── */
        .mobile-navbar {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 56px;
            background: var(--sidebar-bg);
            z-index: 1040;
            padding: 0 16px;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        @media (max-width: 991.98px) {
            .mobile-navbar { display: flex; }
            .sbvs-main { padding-top: 68px; }
        }

        .mobile-toggle {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.3rem;
            cursor: pointer;
            padding: 4px;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1045;
        }

        .sidebar-overlay.show { display: block; }

        /* ── Cards ───────────────────────────────────────────────────────── */
        .card {
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            border: 0;
            background: var(--surface);
        }

        .card-header {
            border-radius: var(--card-radius) var(--card-radius) 0 0 !important;
            border-bottom: 1px solid var(--border);
            background: transparent;
        }

        /* ── KPI Cards ───────────────────────────────────────────────────── */
        .kpi-card {
            border: 0;
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            transition: box-shadow 0.25s, transform 0.25s;
            cursor: default;
        }

        .kpi-card:hover {
            box-shadow: var(--card-shadow-hover);
            transform: translateY(-3px);
        }

        .kpi-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .kpi-value {
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .kpi-label {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        /* ── Page hero / header ──────────────────────────────────────────── */
        .page-header {
            background: linear-gradient(135deg, var(--accent) 0%, #8b5cf6 100%);
            border-radius: var(--card-radius);
            padding: 22px 24px;
            margin-bottom: 1.5rem;
            color: #fff;
        }

        /* ── Tables ──────────────────────────────────────────────────────── */
        .table > thead > tr > th {
            background: #f9f9f9;
            color: #5e6278;
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px dashed var(--border);
            padding: 12px 10px;
            white-space: nowrap;
        }

        .table > tbody > tr > td {
            color: var(--text);
            padding: 12px 10px;
            vertical-align: middle;
            border-bottom: 1px dashed var(--border);
            font-size: 0.875rem;
        }

        .table > tbody > tr:last-child > td { border-bottom: 0; }

        /* ── Action buttons (small icon) ─────────────────────────────────── */
        .btn-action {
            width: 32px; height: 32px;
            border: none;
            border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.82rem;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            margin: 0 2px;
            text-decoration: none;
        }

        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.12); }
        .btn-view   { background: rgba(99,102,241,0.1); color: var(--accent); }
        .btn-edit   { background: rgba(245,158,11,0.1); color: #f59e0b; }
        .btn-delete { background: rgba(239,68,68,0.1);  color: #ef4444; }

        /* ── Badge helpers ───────────────────────────────────────────────── */
        .badge-custom {
            padding: 0.35em 0.7em;
            font-weight: 600;
            border-radius: 6px;
            font-size: 0.7rem;
        }

        .badge-branch {
            background: rgba(14,165,233,0.1);
            color: #0ea5e9;
            border: 1px solid rgba(14,165,233,0.2);
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* ── Modal headers ───────────────────────────────────────────────── */
        .modal-header-accent {
            background: linear-gradient(135deg, var(--accent), #8b5cf6);
            color: #fff;
        }

        .modal-header-warning {
            background: linear-gradient(135deg, #f59e0b, #f97316);
            color: #fff;
        }

        .modal-header-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff;
        }

        /* ── Activity items ───────────────────────────────────────────────── */
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }

        .activity-item:last-child { border-bottom: 0; }
        .activity-item:hover { background: #f9fafb; }

        .avatar-sm {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.85rem;
            flex-shrink: 0;
        }

        /* ── Quick actions card ──────────────────────────────────────────── */
        .quick-actions {
            border: 1px solid rgba(99,102,241,0.15);
            background: rgba(99,102,241,0.03);
        }

        /* ── Chart wrapper ───────────────────────────────────────────────── */
        .chart-wrap {
            position: relative;
            height: 220px;
        }

        /* ── DataTables override ─────────────────────────────────────────── */
        div.dataTables_wrapper div.dataTables_filter input {
            border-radius: 8px;
            border: 1px solid var(--border);
            padding: 4px 10px;
            font-size: 0.85rem;
        }

        div.dataTables_wrapper div.dataTables_length select {
            border-radius: 8px;
            border: 1px solid var(--border);
            padding: 4px 8px;
            font-size: 0.85rem;
        }

        /* ── Fade animations ─────────────────────────────────────────────── */
        .fade-in {
            animation: fadeIn 0.4s ease forwards;
        }

        .fade-up {
            animation: fadeUp 0.5s ease forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Print ───────────────────────────────────────────────────────── */
        @media print {
            .sidebar, .mobile-navbar, .sidebar-overlay { display: none !important; }
            .sbvs-main { margin-left: 0 !important; padding-top: 0 !important; }
        }
    </style>
</head>
