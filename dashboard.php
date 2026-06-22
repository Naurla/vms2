<?php
/**
 * dashboard.php – Main Dashboard
 */
define('BASE_PATH', '');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireAuth();

$db   = getDB();
$user = currentUser();

// ── Stats ─────────────────────────────────────────────────────
$todayVisitors  = (int)$db->query("SELECT COUNT(*) FROM visit_logs WHERE DATE(check_in_time) = CURDATE()")->fetchColumn();
$checkedIn      = (int)$db->query("SELECT COUNT(*) FROM visit_logs WHERE status = 'Checked In'")->fetchColumn();
$totalResidents = (int)$db->query("SELECT COUNT(*) FROM residents WHERE status = 'Active'")->fetchColumn();
$totalVisitors  = (int)$db->query("SELECT COUNT(*) FROM visitors")->fetchColumn();
$monthlyVisits  = (int)$db->query("SELECT COUNT(*) FROM visit_logs WHERE MONTH(check_in_time)=MONTH(CURDATE()) AND YEAR(check_in_time)=YEAR(CURDATE())")->fetchColumn();

// ── Recent visits ─────────────────────────────────────────────
$recentVisits = $db->query("
    SELECT vl.*, v.full_name AS visitor_name, v.contact_phone,
           r.full_name AS resident_name, r.room_number,
           u.full_name AS staff_name
    FROM visit_logs vl
    JOIN visitors v  ON v.id = vl.visitor_id
    JOIN residents r ON r.id = vl.resident_id
    JOIN users u     ON u.id = vl.checked_in_by
    ORDER BY vl.check_in_time DESC
    LIMIT 10
")->fetchAll();

// ── Currently checked in ──────────────────────────────────────
$currentlyIn = $db->query("
    SELECT vl.id, v.full_name AS visitor_name,
           r.full_name AS resident_name, r.room_number,
           vl.check_in_time, vl.relationship, vl.purpose, vl.visit_code
    FROM visit_logs vl
    JOIN visitors v  ON v.id = vl.visitor_id
    JOIN residents r ON r.id = vl.resident_id
    WHERE vl.status = 'Checked In'
    ORDER BY vl.check_in_time DESC
    LIMIT 8
")->fetchAll();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <div>
        <h2>Welcome back, <?= e(explode(' ', $user['full_name'])[0]) ?>! 👋</h2>
        <p>Here's what's happening at the care home today.</p>
    </div>
    <div class="welcome-time">
        <div class="big-time" id="big-time">--:--</div>
        <div class="big-date" id="big-date"></div>
    </div>
</div>

<!-- Stat Cards -->
<div class="stats-grid" id="stat-cards">
    <div class="stat-card teal">
        <div class="stat-icon">📅</div>
        <div class="stat-info">
            <div class="stat-value" id="stat-today"><?= $todayVisitors ?></div>
            <div class="stat-label">Today's Visitors</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-info">
            <div class="stat-value" id="stat-checkedin"><?= $checkedIn ?></div>
            <div class="stat-label">Currently Inside</div>
        </div>
    </div>
    <div class="stat-card gold">
        <div class="stat-icon">👵</div>
        <div class="stat-info">
            <div class="stat-value" id="stat-residents"><?= $totalResidents ?></div>
            <div class="stat-label">Active Residents</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">📊</div>
        <div class="stat-info">
            <div class="stat-value" id="stat-monthly"><?= $monthlyVisits ?></div>
            <div class="stat-label">Visits This Month</div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<?php if (isAdmin()): ?>
<div class="quick-actions mb-24">
    <a href="visitors/list.php" class="quick-card">
        <span class="qc-icon">📋</span>
        <span class="qc-label">Visit Logs</span>
    </a>
    <a href="residents/add.php" class="quick-card">
        <span class="qc-icon">➕</span>
        <span class="qc-label">Add Resident</span>
    </a>
    <a href="residents/list.php" class="quick-card">
        <span class="qc-icon">👵</span>
        <span class="qc-label">Residents</span>
    </a>
    <a href="reports/index.php" class="quick-card">
        <span class="qc-icon">📈</span>
        <span class="qc-label">Reports</span>
    </a>
    <a href="users/list.php" class="quick-card">
        <span class="qc-icon">👤</span>
        <span class="qc-label">Manage Users</span>
    </a>
</div>
<?php endif; ?>

<!-- Kiosk Banner -->
<div style="background:linear-gradient(135deg,var(--accent-light),var(--accent));border-radius:var(--radius-lg);padding:20px 28px;margin-bottom:26px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;box-shadow:var(--shadow-md)">
    <div>
        <div style="font-size:15px;font-weight:900;color:var(--primary-dark);margin-bottom:4px">👥 Visitor Self-Service Kiosk</div>
        <div style="font-size:13px;color:var(--accent-dark);font-weight:600">Share these links with visitors or open on a public terminal.</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="index.php" target="_blank" class="btn btn-outline btn-sm">🏠 Kiosk Home ↗</a>
        <a href="checkin.php" target="_blank" class="btn btn-primary btn-sm">✅ Kiosk Check-In ↗</a>
        <a href="checkout.php" target="_blank" class="btn btn-secondary btn-sm">🚪 Kiosk Check-Out ↗</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;flex-wrap:wrap">

<!-- Currently Inside -->
<div class="card">
    <div class="card-header">
        <div class="card-title">🟢 Currently Inside
            <span class="badge badge-success" style="margin-left:6px"><?= $checkedIn ?></span>
        </div>
        <a href="visitors/checkout.php" class="btn btn-sm btn-success">Check Out →</a>
    </div>
    <?php if ($currentlyIn): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Visitor</th>
                    <th>Resident / Room</th>
                    <th>Since</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($currentlyIn as $ci): ?>
            <tr>
                <td>
                    <div class="td-name">
                        <?= e($ci['visitor_name']) ?>
                        <span class="badge badge-gold" style="font-size:9.5px; margin-left:6px; font-weight:800" title="Check-Out Code"><?= e($ci['visit_code']) ?></span>
                    </div>
                    <div class="td-sub"><?= e($ci['relationship'] ?: '—') ?></div>
                </td>
                <td>
                    <div class="td-name"><?= e($ci['resident_name']) ?></div>
                    <div class="td-sub">Room <?= e($ci['room_number']) ?></div>
                </td>
                <td style="white-space:nowrap;font-size:12px;color:var(--text-muted)">
                    <?= fmtDateTime($ci['check_in_time']) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:30px">
        <div class="empty-icon">🏡</div>
        <p>No visitors currently inside.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Recent Visit Log -->
<div class="card">
    <div class="card-header">
        <div class="card-title">📋 Recent Visit Log</div>
        <a href="visitors/list.php" class="btn btn-sm btn-secondary">View All →</a>
    </div>
    <?php if ($recentVisits): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Visitor</th>
                    <th>Resident</th>
                    <th>Check In</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentVisits as $v): ?>
            <tr>
                <td class="td-name"><?= e($v['visitor_name']) ?></td>
                <td>
                    <div><?= e($v['resident_name']) ?></div>
                    <div class="td-sub">Room <?= e($v['room_number']) ?></div>
                </td>
                <td style="font-size:12px;white-space:nowrap;color:var(--text-muted)">
                    <?= fmtDateTime($v['check_in_time']) ?>
                </td>
                <td><?= statusBadge($v['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:30px">
        <div class="empty-icon">📋</div>
        <p>No visits recorded yet.</p>
    </div>
    <?php endif; ?>
</div>

</div>

<script>
// API endpoint for live stat refresh
const STATS_ENDPOINT = 'api/dashboard_stats.php';
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
