<?php
/**
 * index.php – Kiosk Welcome Landing Page
 * No authentication required.
 */
define('BASE_PATH', './');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Welcome to Care Home Visitor Management System">
    <title>Welcome – Care Home VMS Kiosk</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Nunito', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0d3d4f 0%, #1a5f7a 50%, #c8a45e 100%);
            background-size: 400% 400%;
            animation: bgShift 18s ease infinite;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            color: #1e2c38;
        }
        @keyframes bgShift {
            0% { background-position: 0% 50% }
            50% { background-position: 100% 50% }
            100% { background-position: 0% 50% }
        }

        /* Top Bar */
        .topbar {
            width: 100%;
            max-width: 860px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding: 0 8px;
            color: #fff;
            animation: fadeIn 0.8s ease;
        }
        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .topbar-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        .topbar-name {
            font-size: 18px;
            font-weight: 900;
            line-height: 1;
        }
        .topbar-sub {
            font-size: 12px;
            opacity: 0.75;
            font-weight: 600;
            margin-top: 3px;
        }
        .topbar-clock {
            text-align: right;
            font-weight: 800;
            font-size: 15px;
            opacity: 0.95;
        }
        .topbar-date {
            font-size: 11px;
            opacity: 0.7;
            font-weight: 600;
            margin-top: 2px;
        }

        /* Welcome Card */
        .welcome-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(20px);
            border-radius: 28px;
            width: 100%;
            max-width: 860px;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.3);
            padding: 48px 40px;
            text-align: center;
            animation: cardIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .welcome-title {
            font-size: 28px;
            font-weight: 900;
            color: #0d3d4f;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        .welcome-subtitle {
            font-size: 15px;
            color: #5a6a77;
            font-weight: 600;
            margin-bottom: 40px;
        }

        /* Buttons Grid */
        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 20px;
        }

        /* Option Card Link */
        .option-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 40px 24px;
            border-radius: 20px;
            text-decoration: none;
            background: #fff;
            border: 2px solid #e2eaf0;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
        }
        .option-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 5px;
            background: var(--theme-color, #1a5f7a);
        }

        /* Check-In Theme color */
        .option-card.checkin {
            --theme-color: #10b981;
            --theme-light: rgba(16, 185, 129, 0.08);
            --theme-shadow: rgba(16, 185, 129, 0.25);
        }
        /* Check-Out Theme color */
        .option-card.checkout {
            --theme-color: #c8a45e;
            --theme-light: rgba(200, 164, 94, 0.08);
            --theme-shadow: rgba(200, 164, 94, 0.25);
        }

        .option-card:hover {
            transform: translateY(-6px);
            border-color: var(--theme-color);
            box-shadow: 0 12px 30px var(--theme-shadow);
            background: var(--theme-light);
        }

        .option-icon {
            font-size: 56px;
            margin-bottom: 20px;
            width: 90px;
            height: 90px;
            border-radius: 24px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2eaf0;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }
        .option-card:hover .option-icon {
            transform: scale(1.1) rotate(2deg);
            background: #fff;
            border-color: var(--theme-color);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
        }

        .option-title {
            font-size: 20px;
            font-weight: 800;
            color: #1e2c38;
            margin-bottom: 8px;
        }
        .option-desc {
            font-size: 13.5px;
            color: #7f8c8d;
            line-height: 1.5;
            font-weight: 600;
            max-width: 240px;
        }

        /* Footer */
        .footer-note {
            color: rgba(255, 255, 255, 0.6);
            font-size: 12px;
            font-weight: 600;
            margin-top: 28px;
            text-align: center;
            animation: fadeIn 1s ease;
        }
        a.staff-link {
            color: rgba(255, 255, 255, 0.7);
            font-size: 11px;
            text-decoration: none;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.4);
            font-weight: 700;
            transition: color 0.2s;
        }
        a.staff-link:hover {
            color: #fff;
        }

        @media(max-width: 680px) {
            .options-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .welcome-card {
                padding: 32px 24px;
            }
            .option-card {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>

<!-- Top Bar -->
<div class="topbar">
    <div class="topbar-brand">
        <div class="topbar-icon">🏠</div>
        <div>
            <div class="topbar-name">Care Home VMS</div>
            <div class="topbar-sub">Home for the Aged</div>
        </div>
    </div>
    <div class="topbar-clock">
        <div id="clock">--:--:--</div>
        <div class="topbar-date" id="clock-date"></div>
    </div>
</div>

<!-- Main Kiosk Welcome Area -->
<div class="welcome-card">
    <h1 class="welcome-title">Welcome to Care Home VMS</h1>
    <p class="welcome-subtitle">Please select an option below to complete your check-in or check-out.</p>

    <div class="options-grid">
        <!-- Check-In Option -->
        <a href="checkin.php" class="option-card checkin">
            <div class="option-icon">✅</div>
            <h2 class="option-title">Visitor Check-In</h2>
            <p class="option-desc">Arrived for a visit? Register your details and log check-in here.</p>
        </a>

        <!-- Check-Out Option -->
        <a href="checkout.php" class="option-card checkout">
            <div class="option-icon">🚪</div>
            <h2 class="option-title">Visitor Check-Out</h2>
            <p class="option-desc">Ready to depart? Complete checkout using your code or QR scan.</p>
        </a>
    </div>
</div>

<div class="footer-note">
    Self-Service Kiosk Terminal
    &nbsp;·&nbsp;
    <a href="login.php" class="staff-link">Staff Login</a>
</div>

<script>
function updateClock() {
    const now = new Date();
    const t = now.toLocaleTimeString('en-PH', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    const d = now.toLocaleDateString('en-PH', {weekday:'long',month:'long',day:'numeric',year:'numeric'});
    document.getElementById('clock').textContent = t;
    document.getElementById('clock-date').textContent = d;
}
setInterval(updateClock, 1000);
updateClock();
</script>
</body>
</html>
