<?php
/**
 * reports/index.php – Reports & Analytics (Admin only)
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
requireAdmin();
$db = getDB();

// Date range filter (default: current month)
$dateFrom = get('date_from', date('Y-m-01'));
$dateTo   = get('date_to',   date('Y-m-d'));

// ── Summary Stats ─────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT
        COUNT(*) AS total_visits,
        COUNT(DISTINCT visitor_id) AS unique_visitors,
        COUNT(DISTINCT resident_id) AS residents_visited,
        AVG(duration_minutes) AS avg_duration,
        SUM(num_companions) AS total_companions
    FROM visit_logs
    WHERE DATE(check_in_time) BETWEEN ? AND ?
");
$stmt->execute([$dateFrom, $dateTo]);
$summary = $stmt->fetch();

// ── Daily visits (last 14 days for chart) ────────────────────
$daily = $db->prepare("
    SELECT DATE(check_in_time) AS visit_date, COUNT(*) AS count
    FROM visit_logs
    WHERE DATE(check_in_time) BETWEEN DATE_SUB(?, INTERVAL 13 DAY) AND ?
    GROUP BY DATE(check_in_time)
    ORDER BY visit_date
");
$daily->execute([$dateTo, $dateTo]);
$daily = $daily->fetchAll();

// ── Visits by resident ───────────────────────────────────────
$byResident = $db->prepare("
    SELECT r.full_name, r.room_number, COUNT(vl.id) AS visits
    FROM visit_logs vl
    JOIN residents r ON r.id = vl.resident_id
    WHERE DATE(vl.check_in_time) BETWEEN ? AND ?
    GROUP BY vl.resident_id
    ORDER BY visits DESC
    LIMIT 10
");
$byResident->execute([$dateFrom, $dateTo]);
$byResident = $byResident->fetchAll();

// ── Visits by purpose ────────────────────────────────────────
$byPurpose = $db->prepare("
    SELECT purpose, COUNT(*) AS count
    FROM visit_logs
    WHERE DATE(check_in_time) BETWEEN ? AND ?
    GROUP BY purpose
    ORDER BY count DESC
");
$byPurpose->execute([$dateFrom, $dateTo]);
$byPurpose = $byPurpose->fetchAll();

// ── Recent completed visits for this period ──────────────────
$recentLogs = $db->prepare("
    SELECT vl.*, v.full_name AS visitor_name, r.full_name AS resident_name, r.room_number
    FROM visit_logs vl
    JOIN visitors v  ON v.id = vl.visitor_id
    JOIN residents r ON r.id = vl.resident_id
    WHERE DATE(vl.check_in_time) BETWEEN ? AND ?
      AND vl.status = 'Checked Out'
    ORDER BY vl.check_in_time DESC
    LIMIT 30
");
$recentLogs->execute([$dateFrom, $dateTo]);
$recentLogs = $recentLogs->fetchAll();

// Chart.js data
$chartLabels = [];
$chartData   = [];
$allDates    = [];
$start = new DateTime($dateTo);
$start->modify('-13 days');
for ($d = clone $start; $d <= new DateTime($dateTo); $d->modify('+1 day')) {
    $allDates[$d->format('Y-m-d')] = 0;
}
foreach ($daily as $row) $allDates[$row['visit_date']] = (int)$row['count'];
foreach ($allDates as $date => $cnt) {
    $chartLabels[] = date('M d', strtotime($date));
    $chartData[]   = $cnt;
}

$pageTitle   = 'Reports & Analytics';
$activeNav   = 'reports';
$breadcrumbs = [['label' => 'Reports']];

$extraScripts = '
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById("visits-chart").getContext("2d");
new Chart(ctx, {
    type: "bar",
    data: {
        labels: ' . json_encode($chartLabels) . ',
        datasets: [{
            label: "Daily Visits",
            data: ' . json_encode($chartData) . ',
            backgroundColor: "rgba(26,95,122,.75)",
            borderColor: "rgba(26,95,122,1)",
            borderWidth: 2,
            borderRadius: 6,
            hoverBackgroundColor: "rgba(200,164,94,.85)"
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ctx.parsed.y + " visits" } }
        },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: "#e2eaf0" } },
            x: { grid: { display: false } }
        }
    }
});
</script>';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Date Filter -->
<div class="card mb-20">
    <div class="card-body" style="padding:16px 22px">
        <form method="GET" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
            <div class="form-group">
                <label>From Date</label>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="form-group">
                <label>To Date</label>
                <input type="date" name="date_to" value="<?= e($dateTo) ?>">
            </div>
            <div style="padding-bottom:1px;display:flex;gap:8px">
                <button type="submit" class="btn btn-primary btn-sm">📊 Generate Report</button>
                <a href="?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-secondary btn-sm">This Month</a>
                <a href="?date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-secondary btn-sm">Today</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid mb-24">
    <div class="stat-card teal">
        <div class="stat-icon">📋</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)($summary['total_visits'] ?? 0) ?></div>
            <div class="stat-label">Total Visits</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">👤</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)($summary['unique_visitors'] ?? 0) ?></div>
            <div class="stat-label">Unique Visitors</div>
        </div>
    </div>
    <div class="stat-card gold">
        <div class="stat-icon">👵</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)($summary['residents_visited'] ?? 0) ?></div>
            <div class="stat-label">Residents Visited</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">⏱️</div>
        <div class="stat-info">
            <div class="stat-value"><?= $summary['avg_duration'] ? round($summary['avg_duration']) : '—' ?><?= $summary['avg_duration'] ? 'm' : '' ?></div>
            <div class="stat-label">Avg Visit Duration</div>
        </div>
    </div>
</div>

<!-- Chart -->
<div class="card mb-22">
    <div class="card-header">
        <div class="card-title">📈 Daily Visits – Last 14 Days</div>
    </div>
    <div class="card-body">
        <canvas id="visits-chart" height="90"></canvas>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px">

<!-- Most Visited Residents -->
<div class="card">
    <div class="card-header"><div class="card-title">👵 Most Visited Residents</div></div>
    <?php if ($byResident): ?>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Resident</th><th>Room</th><th>Visits</th></tr></thead>
            <tbody>
            <?php foreach ($byResident as $br): ?>
            <tr>
                <td class="td-name"><?= e($br['full_name']) ?></td>
                <td><span class="badge badge-gold">Rm <?= e($br['room_number']) ?></span></td>
                <td><span class="badge badge-primary"><?= $br['visits'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:24px"><p>No data for this period.</p></div>
    <?php endif; ?>
</div>

<!-- Visits by Purpose -->
<div class="card">
    <div class="card-header"><div class="card-title">🎯 Visits by Purpose</div></div>
    <?php if ($byPurpose): ?>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Purpose</th><th>Count</th></tr></thead>
            <tbody>
            <?php
            $maxCount = $byPurpose[0]['count'] ?? 1;
            foreach ($byPurpose as $bp):
                $pct = round(($bp['count'] / $maxCount) * 100);
            ?>
            <tr>
                <td><?= e($bp['purpose'] ?: 'Unspecified') ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px">
                        <div style="flex:1;background:var(--border);border-radius:20px;height:8px;overflow:hidden">
                            <div style="width:<?= $pct ?>%;background:var(--primary);height:100%;border-radius:20px;transition:width .6s"></div>
                        </div>
                        <span style="font-weight:700;color:var(--primary);min-width:24px"><?= $bp['count'] ?></span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:24px"><p>No data for this period.</p></div>
    <?php endif; ?>
</div>

</div>

<!-- Completed Visits Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">✅ Completed Visits in Period</div>
        <span style="color:var(--text-muted);font-size:13px"><?= plural(count($recentLogs), 'record') ?></span>
    </div>
    <?php if ($recentLogs): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Visitor</th>
                    <th>Resident / Room</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentLogs as $log): ?>
            <tr>
                <td class="td-name"><?= e($log['visitor_name']) ?></td>
                <td>
                    <?= e($log['resident_name']) ?>
                    <div class="td-sub">Room <?= e($log['room_number']) ?></div>
                </td>
                <td style="font-size:12px;white-space:nowrap"><?= fmtDateTime($log['check_in_time']) ?></td>
                <td style="font-size:12px;white-space:nowrap"><?= fmtDateTime($log['check_out_time']) ?></td>
                <td><?= fmtDuration($log['duration_minutes']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="empty-icon">📊</div><p>No completed visits in this date range.</p></div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
