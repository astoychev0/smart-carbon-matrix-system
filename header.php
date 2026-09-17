<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Проверка дали потребителят е влезнал в системата
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header("Location: login.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
$logged_user  = $_SESSION['user_name'] ?? 'Александър Стойчев';
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STORAGE MATRIX SYSTEM - Ottobock</title>
    <style>
        :root {
            --primary-blue: #003896;
            --primary-blue-hover: #002868;
            --accent-blue: #0284c7;
            --gradient-blue: linear-gradient(135deg, #003896 0%, #0284c7 100%);
            --bg-light: #f1f5f9;
            --card-bg: #ffffff;
            --border-color: #cbd5e1;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --status-green: #10b981;
            --status-red: #ef4444;
            --shadow-soft: 0 4px 15px -1px rgba(0, 56, 150, 0.08), 0 2px 6px -1px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-main);
            line-height: 1.5;
            background-image: radial-gradient(#cbd5e1 0.75px, transparent 0.75px);
            background-size: 24px 24px;
            overflow-x: hidden;
            min-height: 100vh;
        }

        body::before {
            content: '';
            display: block;
            height: 5px;
            background: var(--gradient-blue);
            width: 100%;
        }

        .app-header {
            background-color: #ffffff;
            border-bottom: 1px solid var(--border-color);
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-soft);
            gap: 10px;
            flex-wrap: nowrap;
            width: 100%;
        }

        .brand-block {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            cursor: pointer;
            user-select: none;
            flex-shrink: 0;
        }

        .brand-logo {
            height: 34px;
            width: auto;
            object-fit: contain;
        }

        .brand-divider {
            width: 2px;
            height: 26px;
            background: var(--gradient-blue);
            border-radius: 2px;
        }

        .system-title h1 {
            font-size: 0.95rem;
            color: var(--primary-blue);
            font-weight: 800;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .system-title span {
            font-size: 0.65rem;
            color: var(--text-muted);
            font-weight: 600;
            white-space: nowrap;
        }

        .app-navigation {
            display: flex;
            gap: 4px;
            background: var(--bg-light);
            padding: 4px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            flex-shrink: 1;
            min-width: 0;
        }

        .nav-link {
            padding: 7px 10px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-main);
            font-weight: 700;
            font-size: 0.78rem;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
            transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
        }

        .nav-link:hover {
            background: #e2e8f0;
            border-color: #94a3b8;
            color: var(--primary-blue);
        }

        .nav-link.active {
            background: var(--gradient-blue);
            color: #ffffff;
            border-color: var(--primary-blue);
            box-shadow: 0 4px 10px rgba(0, 56, 150, 0.2);
        }

        .user-badge-block {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            padding: 6px 12px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-soft);
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--primary-blue);
            flex-shrink: 0;
            white-space: nowrap;
        }

        .btn-link-logout {
            background: none;
            border: none;
            color: var(--status-red);
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            margin-left: 2px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-link-logout:hover {
            text-decoration: underline;
        }

        .app-container {
            padding: 35px 40px;
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
        }

        .content-panel {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 28px;
            box-shadow: var(--shadow-soft);
            position: relative;
            width: 100%;
        }
    </style>
</head>
<body>

    <!-- Горна лента (Header) -->
    <header class="app-header">
        <a href="index.php" class="brand-block">
            <img src="logo-ottobock 3.png" alt="Ottobock Logo" class="brand-logo">
            <div class="brand-divider"></div>
            <div class="system-title">
                <h1>STORAGE MATRIX SYSTEM</h1>
                <span>SMART LED CONTROL TERMINAL</span>
            </div>
        </a>

        <!-- Навигация с включен бутон "Продукти" -->
        <nav class="app-navigation">
            <a href="index.php" class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>">🔍 Търсене</a>
            <a href="products.php" class="nav-link <?= ($current_page == 'products.php') ? 'active' : '' ?>">📦 Продукти</a>
            <a href="matrici.php" class="nav-link <?= ($current_page == 'matrici.php' || $current_page == 'matrices.php') ? 'active' : '' ?>">⚙️ Матрици</a>
            <a href="locations.php" class="nav-link <?= ($current_page == 'locations.php') ? 'active' : '' ?>">📍 Локации</a>
            <a href="reports.php" class="nav-link <?= ($current_page == 'reports.php') ? 'active' : '' ?>">📊 Справки</a>
        </nav>

        <!-- Потребителски профил и Изход -->
        <div class="user-badge-block">
            <span>👤 <?= htmlspecialchars($logged_user) ?></span>
            <a href="logout.php" class="btn-link-logout">Изход</a>
        </div>
    </header>

    <!-- Основен контейнер -->
    <main class="app-container">
        <div class="content-panel">