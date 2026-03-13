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

    <!-- Google Fonts: Inter Variable (2025 — variable axis for crisp rendering) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">

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
        /* ═══════════════════════════════════════════════════════════════
           SBVS Portal — Design System 2025/2026
           ═══════════════════════════════════════════════════════════════ */

        /* ── Design Tokens ─────────────────────────────────────────────── */
        :root {
            --font-base: 'Inter', system-ui, -apple-system, sans-serif;

            /* ── Sidebar ── */
            --sidebar-w:           260px;
            --sidebar-bg:          #0d1117;
            --sidebar-hover:       rgba(255,255,255,0.055);
            --sidebar-active:      rgba(99,102,241,0.18);
            --sidebar-text:        #848699;
            --sidebar-text-hover:  #c8cad8;
            --sidebar-text-active: #ffffff;

            /* ── Brand accent ── */
            --accent:        #6366f1;
            --accent-2:      #818cf8;
            --accent-hover:  #4f46e5;
            --accent-light:  rgba(99,102,241,0.08);
            --accent-mid:    rgba(99,102,241,0.16);

            /* ── App surfaces ── */
            --bg:         #f1f5f9;
            --surface:    #ffffff;
            --surface-2:  #f8fafc;
            --border:     #e2e8f0;
            --border-s:   rgba(0,0,0,0.06);

            /* ── Text ── */
            --text:       #1e293b;
            --text-2:     #475569;
            --text-muted: #94a3b8;

            /* ── Card ── */
            --card-radius: 14px;
            --card-shadow: 0 1px 3px rgba(0,0,0,0.04),
                           0 8px 28px rgba(0,0,0,0.05);
            --card-shadow-hover: 0 4px 8px rgba(0,0,0,0.06),
                                 0 20px 48px rgba(0,0,0,0.09);
        }

        /* ── Reset & base ───────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-base);
            background: var(--bg);
            color: var(--text);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            line-height: 1.6;
            font-size: 15px;
        }

        /* ── Global scrollbar ───────────────────────────────────────────── */
        ::-webkit-scrollbar       { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.14); border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.26); }

        /* ── App layout ─────────────────────────────────────────────────── */
        .sbvs-layout { display: flex; min-height: 100vh; }

        .sbvs-main {
            flex: 1;
            padding: 28px 28px 48px;
            margin-left: var(--sidebar-w);
            transition: margin-left 0.3s ease;
            min-width: 0;
        }

        @media (max-width: 991.98px) {
            .sbvs-main { margin-left: 0; padding: 16px 16px 32px; }
        }

        /* ══════════════════════════════════════════════════════════════════
           SIDEBAR 2025 — Pill navigation
           ══════════════════════════════════════════════════════════════════ */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--sidebar-bg);
            position: fixed;
            top: 0; left: 0;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1050;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s cubic-bezier(0.16,1,0.3,1);
            border-right: 1px solid rgba(255,255,255,0.04);
        }

        .sidebar::-webkit-scrollbar { width: 3px; }
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.08); border-radius: 2px;
        }

        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
        }

        /* Sidebar: Brand ─────────────────────────── */
        .sidebar-brand {
            padding: 20px 18px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .sidebar-brand-icon {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, var(--accent) 0%, #8b5cf6 100%);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(99,102,241,0.35);
        }

        .sidebar-brand-icon i { font-size: 1.05rem; color: #fff; }
        .sidebar-brand-text { line-height: 1.25; }

        .sidebar-brand-name {
            font-size: 0.88rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.02em;
        }

        .sidebar-brand-sub {
            font-size: 0.62rem;
            color: var(--sidebar-text);
            text-transform: uppercase;
            letter-spacing: 0.9px;
        }

        /* Sidebar: User strip ────────────────────── */
        .sidebar-user {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-user-avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg,
                rgba(99,102,241,0.3), rgba(139,92,246,0.3));
            color: #a5b4fc;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.82rem;
            flex-shrink: 0;
            border: 1.5px solid rgba(99,102,241,0.35);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }

        .sidebar-user-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: #e2e4f0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-user-role {
            font-size: 0.63rem;
            color: var(--sidebar-text);
            white-space: nowrap;
        }

        /* Sidebar: Nav ───────────────────────────── */
        .sidebar-nav { flex: 1; padding: 10px 0; }

        .sidebar-section-label {
            font-size: 0.58rem;
            font-weight: 700;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: var(--sidebar-text);
            padding: 16px 20px 5px;
            opacity: 0.55;
        }

        /* ── Pill links (key 2025 change) ── */
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 2px 10px;
            padding: 9px 12px;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 500;
            border-radius: 10px;
            transition: background 0.18s ease, color 0.18s ease;
            position: relative;
        }

        .sidebar-link:hover {
            background: var(--sidebar-hover);
            color: var(--sidebar-text-hover);
        }

        .sidebar-link.active {
            background: var(--sidebar-active);
            color: var(--sidebar-text-active);
            font-weight: 600;
        }

        .sidebar-link.active i { color: var(--accent-2); }

        .sidebar-link i {
            width: 18px;
            font-size: 0.98rem;
            flex-shrink: 0;
            text-align: center;
            transition: color 0.18s;
        }

        .sidebar-badge {
            margin-left: auto;
            font-size: 0.6rem;
            padding: 2px 7px;
            border-radius: 20px;
            font-weight: 700;
            background: rgba(99,102,241,0.2);
            color: #a5b4fc;
        }

        /* Sidebar: Footer ───────────────────────── */
        .sidebar-footer {
            padding: 12px 14px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .sidebar-logout {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 9px 12px;
            color: #f87171;
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 600;
            border-radius: 10px;
            transition: background 0.18s, border-color 0.18s;
            border: 1px solid rgba(248,113,113,0.18);
        }

        .sidebar-logout:hover {
            background: rgba(248,113,113,0.1);
            color: #fca5a5;
            border-color: rgba(248,113,113,0.3);
        }

        /* ── Mobile navbar ─────────────────────────────────────────────── */
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
            backdrop-filter: blur(12px);
        }

        @media (max-width: 991.98px) {
            .mobile-navbar { display: flex; }
            .sbvs-main { padding-top: 72px; }
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
            background: rgba(0,0,0,0.55);
            z-index: 1045;
            backdrop-filter: blur(2px);
        }

        .sidebar-overlay.show { display: block; }

        /* ── Mobile bottom navigation bar ──────────────────────────────── */
        .mobile-bottom-nav {
            display: none;
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: auto;
            background: var(--sidebar-bg);
            border-top: 1px solid rgba(255,255,255,0.06);
            z-index: 1040;
            padding: 4px 0 max(4px, env(safe-area-inset-bottom));
        }

        @media (max-width: 991.98px) {
            .mobile-bottom-nav { display: flex; }
            .sbvs-main { padding-bottom: 72px; }
        }

        .bnav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            padding: 6px 4px;
            color: #848699;
            font-size: 0.58rem;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.18s;
            letter-spacing: 0.2px;
        }

        .bnav-item i { font-size: 1.15rem; }

        .bnav-item:hover,
        .bnav-item.active { color: var(--accent-2); }


           ══════════════════════════════════════════════════════════════════ */
        .card {
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-s);
            background: var(--surface);
        }

        .card-header {
            border-radius: calc(var(--card-radius) - 1px)
                           calc(var(--card-radius) - 1px) 0 0 !important;
            border-bottom: 1px solid var(--border);
            background: transparent;
            padding: 14px 18px;
        }

        /* ── KPI Cards ─────────────────────────────────────────────────── */
        .kpi-card {
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            transition: box-shadow 0.25s, transform 0.25s;
            cursor: default;
            overflow: hidden;
            position: relative;
        }

        /* Colored top accent line via CSS variable */
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: var(--kpi-accent, transparent);
            border-radius: var(--card-radius) var(--card-radius) 0 0;
        }

        .kpi-card:hover {
            box-shadow: var(--card-shadow-hover);
            transform: translateY(-2px);
        }

        .kpi-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .kpi-value {
            font-size: 1.65rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.04em;
        }

        .kpi-label {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* ── Page header — mesh gradient ───────────────────────────────── */
        .page-header {
            background: linear-gradient(135deg,
                #4f46e5 0%, #7c3aed 45%, #0891b2 100%);
            border-radius: var(--card-radius);
            padding: 22px 24px;
            margin-bottom: 1.5rem;
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        /* Dot-grid mesh overlay */
        .page-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle, rgba(255,255,255,0.15) 1px, transparent 1px);
            background-size: 20px 20px;
            pointer-events: none;
        }

        /* Glow orb inside header */
        .page-header::after {
            content: '';
            position: absolute;
            right: -60px;
            top: -60px;
            width: 220px; height: 220px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
            pointer-events: none;
        }

        /* ── Tables ─────────────────────────────────────────────────────── */
        .table > thead > tr > th {
            background: var(--surface-2, #f8fafc);
            color: var(--text-2, #475569);
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-bottom: 1px solid var(--border);
            border-top: none;
            padding: 12px 12px;
            white-space: nowrap;
        }

        .table > tbody > tr > td {
            color: var(--text);
            padding: 12px 12px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            font-size: 0.875rem;
        }

        .table > tbody > tr:last-child > td { border-bottom: 0; }

        .table > tbody > tr { transition: background 0.12s; }
        .table > tbody > tr:hover > td { background: var(--surface-2, #f8fafc); }

        /* ── Action buttons ─────────────────────────────────────────────── */
        .btn-action {
            width: 32px; height: 32px;
            border: none;
            border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.82rem;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
            margin: 0 2px;
            text-decoration: none;
        }

        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.12);
        }

        .btn-view   { background: rgba(99,102,241,0.1); color: var(--accent); }
        .btn-edit   { background: rgba(245,158,11,0.1); color: #f59e0b; }
        .btn-delete { background: rgba(239,68,68,0.1);  color: #ef4444; }
        .btn-view:hover   { background: rgba(99,102,241,0.18); }
        .btn-edit:hover   { background: rgba(245,158,11,0.18); }
        .btn-delete:hover { background: rgba(239,68,68,0.18); }

        /* ── Badge helpers ──────────────────────────────────────────────── */
        .badge-custom {
            padding: 0.3em 0.75em;
            font-weight: 600;
            border-radius: 20px;
            font-size: 0.7rem;
            letter-spacing: 0.2px;
        }

        .badge-branch {
            background: rgba(14,165,233,0.1);
            color: #0ea5e9;
            border: 1px solid rgba(14,165,233,0.2);
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
        }

        /* ── Modal headers ──────────────────────────────────────────────── */
        .modal-header-accent  { background: linear-gradient(135deg, var(--accent), #8b5cf6); color: #fff; }
        .modal-header-warning { background: linear-gradient(135deg, #f59e0b, #f97316);       color: #fff; }
        .modal-header-danger  { background: linear-gradient(135deg, #ef4444, #dc2626);       color: #fff; }

        /* ── Activity items ─────────────────────────────────────────────── */
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            transition: background 0.12s;
        }

        .activity-item:last-child { border-bottom: 0; }
        .activity-item:hover { background: var(--surface-2, #f8fafc); }

        .avatar-sm {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.85rem;
            flex-shrink: 0;
        }

        /* ── Quick actions card ─────────────────────────────────────────── */
        .quick-actions {
            border: 1px solid rgba(99,102,241,0.15);
            background: linear-gradient(135deg,
                rgba(99,102,241,0.03) 0%, rgba(139,92,246,0.03) 100%);
        }

        /* ── Chart wrapper ──────────────────────────────────────────────── */
        .chart-wrap { position: relative; height: 220px; }

        /* ── DataTables override ────────────────────────────────────────── */
        div.dataTables_wrapper div.dataTables_filter input {
            border-radius: 8px;
            border: 1px solid var(--border);
            padding: 6px 12px;
            font-size: 0.84rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        div.dataTables_wrapper div.dataTables_filter input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }

        div.dataTables_wrapper div.dataTables_length select {
            border-radius: 8px;
            border: 1px solid var(--border);
            padding: 6px 10px;
            font-size: 0.84rem;
        }

        /* ── Staggered fade-up animations ───────────────────────────────── */
        .fade-in  { animation: fadeIn  0.4s ease both; }
        .fade-up  { animation: fadeUp  0.5s cubic-bezier(0.16,1,0.3,1) both; }

        .fade-up:nth-child(1) { animation-delay: 0.00s; }
        .fade-up:nth-child(2) { animation-delay: 0.06s; }
        .fade-up:nth-child(3) { animation-delay: 0.12s; }
        .fade-up:nth-child(4) { animation-delay: 0.18s; }
        .fade-up:nth-child(5) { animation-delay: 0.24s; }
        .fade-up:nth-child(6) { animation-delay: 0.30s; }

        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Focus rings (accessibility + modern look) ──────────────────── */
        :focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }

        /* ── Bootstrap button accent override ───────────────────────────── */
        .btn-primary {
            background: var(--accent);
            border-color: var(--accent);
        }
        .btn-primary:hover, .btn-primary:focus {
            background: var(--accent-hover);
            border-color: var(--accent-hover);
        }

        /* ══════════════════════════════════════════════════════════════════
           RESPONSIVE — Mobile-first polish
           ══════════════════════════════════════════════════════════════════ */

        /* ── Modals: full-screen on phones (< 576px) ────────────────────── */
        @media (max-width: 575.98px) {
            .modal-dialog {
                margin: 0;
                max-width: 100%;
                width: 100%;
                min-height: 100dvh;
                align-items: flex-end;
            }
            .modal-content {
                border-radius: 20px 20px 0 0 !important;
                min-height: 30dvh;
            }
            .modal-dialog-centered {
                align-items: flex-end;
                min-height: 100dvh;
            }
        }

        /* ── KPI cards: smaller value on very small screens ─────────────── */
        @media (max-width: 400px) {
            .kpi-value  { font-size: 1.25rem; }
            .kpi-icon   { width: 36px; height: 36px; font-size: 0.9rem; }
        }

        /* ── Page header: tighter padding on phones ─────────────────────── */
        @media (max-width: 575.98px) {
            .page-header {
                padding: 16px 18px;
            }
            .page-header h4, .page-header h5 {
                font-size: 1.05rem !important;
            }
        }

        /* ── Cards: less padding on phones ──────────────────────────────── */
        @media (max-width: 575.98px) {
            .card-body  { padding: 14px 14px !important; }
            .card-header { padding: 10px 14px !important; }
        }

        /* ── Tables: smaller font on xs so more data fits ───────────────── */
        @media (max-width: 575.98px) {
            .table > thead > tr > th,
            .table > tbody > tr > td {
                font-size: 0.8rem;
                padding: 9px 8px;
            }
        }

        /* ── Action buttons: bigger tap targets on mobile ───────────────── */
        @media (max-width: 767.98px) {
            .btn-action {
                width: 36px;
                height: 36px;
                font-size: 0.88rem;
            }
        }

        /* ── DataTables toolbar: wrap on narrow screens ─────────────────── */
        @media (max-width: 575.98px) {
            div.dataTables_wrapper div.dataTables_filter,
            div.dataTables_wrapper div.dataTables_length {
                text-align: left;
                float: none;
                margin-bottom: 8px;
            }
            div.dataTables_wrapper div.dataTables_filter input {
                width: 100%;
                margin-left: 0;
            }
            div.dataTables_wrapper div.dataTables_info,
            div.dataTables_wrapper div.dataTables_paginate {
                text-align: center;
                float: none;
                margin-top: 8px;
            }
        }

        /* ── Charts: shorter height on phones ───────────────────────────── */
        @media (max-width: 575.98px) {
            .chart-wrap { height: 180px; }
        }

        /* ── Forms inside modals: full-width on xs ───────────────────────── */
        @media (max-width: 575.98px) {
            .row.g-3 > [class*="col-md"],
            .row.g-2 > [class*="col-sm"] {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        /* ── Print ──────────────────────────────────────────────────────── */
        @media print {
            .sidebar, .mobile-navbar, .sidebar-overlay { display: none !important; }
            .sbvs-main { margin-left: 0 !important; padding-top: 0 !important; }
        }
    </style>
</head>
