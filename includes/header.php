<?php
/**
 * includes/header.php
 * Shared HTML head, sidebar navigation, and top header.
 *
 * Required before include:
 *   define('BASE_PATH', '../');   // for sub-directory pages
 *   define('BASE_PATH', '');      // for root-level pages
 *   $pageTitle = 'My Page';
 *   $activeNav = 'dashboard';     // matches nav item keys
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$user   = currentUser();
$flash  = getFlash();
$bp     = defined('BASE_PATH') ? BASE_PATH : '';
$pTitle = $pageTitle ?? 'Care Home VMS';
$active = $activeNav ?? '';

// Breadcrumb (optional): set $breadcrumbs = [['label'=>'Page','url'=>'page.php']]
$breadcrumbs = $breadcrumbs ?? [];

// Current check-in count for badge
$checkedInBadge = 0;
try {
    $checkedInBadge = checkedInCount(getDB());
} catch (Throwable) {}

// Initials from full name
$initials = implode('', array_map(fn($w) => strtoupper($w[0]),
    array_slice(explode(' ', trim($user['full_name'])), 0, 2)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Visitor Management System – Home for the Aged | <?= e($pTitle) ?>">
    <title><?= e($pTitle) ?> – Care Home VMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $bp ?>assets/css/style.css">
</head>
<body>

<?php if ($flash): ?>
<div id="php-flash"
     data-message="<?= e($flash['message']) ?>"
     data-type="<?= e($flash['type']) ?>"
     style="display:none"></div>
<?php endif; ?>

<div class="app-wrapper">

<!-- ══════════════ SIDEBAR ══════════════ -->
<aside class="sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon">🏠</div>
        <div class="brand-text">
            <div class="brand-name">Care Home VMS</div>
            <div class="brand-sub">Home for the Aged</div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">

        <div class="nav-label">Main</div>

        <a href="<?= $bp ?>dashboard.php"
           class="nav-item <?= $active === 'dashboard' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span> Dashboard
        </a>

        <?php if ($user['role'] === 'admin'): ?>

        <div class="nav-label">Admin – Visitors</div>

        <a href="<?= $bp ?>visitors/list.php"
           class="nav-item <?= $active === 'visit-log' ? 'active' : '' ?>">
            <span class="nav-icon">📋</span> Visit Logs
            <?php if ($checkedInBadge > 0): ?>
            <span class="nav-badge" id="nav-badge-checkedin"><?= $checkedInBadge ?> in</span>
            <?php endif; ?>
        </a>

        <div class="nav-label">Admin – Residents</div>

        <a href="<?= $bp ?>residents/list.php"
           class="nav-item <?= $active === 'residents' ? 'active' : '' ?>">
            <span class="nav-icon">👵</span> Residents
        </a>

        <a href="<?= $bp ?>residents/add.php"
           class="nav-item <?= $active === 'resident-add' ? 'active' : '' ?>">
            <span class="nav-icon">➕</span> Add Resident
        </a>

        <div class="nav-label">Admin – Reports</div>

        <a href="<?= $bp ?>reports/index.php"
           class="nav-item <?= $active === 'reports' ? 'active' : '' ?>">
            <span class="nav-icon">📈</span> Reports
        </a>

        <a href="<?= $bp ?>users/list.php"
           class="nav-item <?= $active === 'users' ? 'active' : '' ?>">
            <span class="nav-icon">👤</span> Manage Users
        </a>

        <?php endif; ?>

        <div class="nav-label">Visitor Kiosk</div>

        <a href="<?= $bp ?>index.php" class="nav-item" target="_blank">
            <span class="nav-icon">🏠</span> Kiosk Home
            <span style="font-size:9px;opacity:.6;margin-left:auto">↗</span>
        </a>

        <a href="<?= $bp ?>checkin.php" class="nav-item" target="_blank">
            <span class="nav-icon">✅</span> Kiosk Check-In
            <span style="font-size:9px;opacity:.6;margin-left:auto">↗</span>
        </a>

        <a href="<?= $bp ?>checkout.php" class="nav-item" target="_blank">
            <span class="nav-icon">🚪</span> Kiosk Check-Out
            <span style="font-size:9px;opacity:.6;margin-left:auto">↗</span>
        </a>

        <div class="nav-label">Session</div>
        <a href="<?= $bp ?>logout.php" class="nav-item logout-nav">
            <span class="nav-icon">🚪</span> Logout
        </a>

    </nav>

    <!-- User info & logout -->
    <div class="sidebar-footer">
        <div class="user-avatar"><?= e($initials) ?></div>
        <div class="user-info">
            <div class="user-name"><?= e($user['full_name']) ?></div>
            <div class="user-role"><?= e(ucfirst($user['role'])) ?></div>
        </div>
        <a href="<?= $bp ?>logout.php" class="logout-btn" title="Logout">🚪</a>
    </div>

</aside>
<!-- ══════════════ /SIDEBAR ══════════════ -->

<!-- ══════════════ MAIN CONTENT ══════════════ -->
<div class="main-content">

    <!-- Top Header -->
    <header class="top-header">
        <div class="page-title-area">
            <div class="page-title"><?= e($pTitle) ?></div>
            <?php if ($breadcrumbs): ?>
            <div class="breadcrumb">
                <a href="<?= $bp ?>dashboard.php">Home</a>
                <?php foreach ($breadcrumbs as $crumb): ?>
                    <span>›</span>
                    <?php if (!empty($crumb['url'])): ?>
                        <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
                    <?php else: ?>
                        <span><?= e($crumb['label']) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="header-right">
            <div style="text-align: right">
                <div class="live-clock" id="live-clock">--:--:--</div>
                <div class="header-date" id="live-date"></div>
            </div>
            <a href="<?= $bp ?>logout.php" class="btn btn-outline-danger btn-sm" style="display: flex; align-items: center; gap: 6px; font-weight: 700;">
                🚪 Logout
            </a>
        </div>
    </header>

    <!-- Page Content -->
    <main class="page-content">
