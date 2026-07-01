<?php
/**
 * reports/logs.php – View Activity Logs
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
requireAdmin();
$db = getDB();

$logs = $db->query("
    SELECT al.*, u.full_name AS user_name, u.role AS user_role
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 200
")->fetchAll();

$pageTitle   = 'Activity Logs';
$activeNav   = 'reports-logs';
$breadcrumbs = [['label' => 'Reports'], ['label' => 'Activity Logs']];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-actions">
    <div class="page-actions-left">
        <strong style="color:var(--text-secondary)"><?= count($logs) ?> Recent Activities</strong>
    </div>
    <div class="page-actions-right">
        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" id="search-logs" placeholder="Search logs…">
        </div>
    </div>
</div>

<div class="card">
    <div class="table-wrapper">
        <table id="logs-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
                    <?= fmtDateTime($log['created_at']) ?>
                </td>
                <td>
                    <?php if ($log['user_name']): ?>
                        <div style="font-weight:700;"><?= e($log['user_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?= ucfirst(e($log['user_role'])) ?></div>
                    <?php else: ?>
                        <span style="color:var(--text-muted);font-style:italic;">System / Deleted User</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge badge-primary"><?= e($log['action']) ?></span>
                </td>
                <td style="font-size:13px;">
                    <?= nl2br(e($log['details'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <tr>
                <td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted);">
                    No activity logs recorded yet.
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
initTableSearch('search-logs', 'logs-table');
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
