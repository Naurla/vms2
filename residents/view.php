<?php
/**
 * residents/view.php – View Resident Profile
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
$db = getDB();

$id = (int)get('id');
if (!$id) redirect('../residents/list.php');

$resident = $db->prepare("SELECT * FROM residents WHERE id = ?");
$resident->execute([$id]);
$resident = $resident->fetch();
if (!$resident) { setFlash('error', 'Resident not found.'); redirect('../residents/list.php'); }

$visits = $db->prepare("
    SELECT vl.*, v.full_name AS visitor_name, v.contact_phone, v.id_type,
           (SELECT GROUP_CONCAT(CONCAT(vc.full_name, ' (', vc.relationship, ')') SEPARATOR ', ')
            FROM visit_companions vc
            WHERE vc.visit_log_id = vl.id) AS companion_details
    FROM visit_logs vl
    JOIN visitors v ON v.id = vl.visitor_id
    WHERE vl.resident_id = ?
    ORDER BY vl.check_in_time DESC
    LIMIT 50
");

$visits->execute([$id]);
$visits = $visits->fetchAll();

$residentLogs = $db->prepare("
    SELECT al.*, u.full_name AS user_name, u.role AS user_role
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    WHERE al.resident_id = ?
    ORDER BY al.created_at DESC
");
$residentLogs->execute([$id]);
$residentLogs = $residentLogs->fetchAll();

// Stats
$totalVisits  = count($visits);
$checkedInNow = count(array_filter($visits, fn($v) => $v['status'] === 'Checked In'));
$avgDuration  = $totalVisits ? round(array_sum(array_column($visits, 'duration_minutes')) / max(1, count(array_filter($visits, fn($v) => $v['duration_minutes'] !== null)))) : 0;

$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $resident['full_name']), 0, 2)));

$pageTitle   = e($resident['full_name']);
$activeNav   = 'residents';
$breadcrumbs = [
    ['label' => 'Residents', 'url' => '../residents/list.php'],
    ['label' => e($resident['full_name'])]
];
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Profile Header Card -->
<div class="card mb-20" style="background:linear-gradient(135deg,var(--primary-dark),var(--primary));color:#fff;border:none">
    <div class="card-body" style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
        <div style="width:80px;height:80px;background:rgba(255,255,255,.2);border-radius:50%;
                    display:flex;align-items:center;justify-content:center;
                    font-size:32px;font-weight:900;color:#fff;
                    border:3px solid rgba(255,255,255,.4);flex-shrink:0">
            <?= e($initials) ?>
        </div>
        <div style="flex:1">
            <h2 style="font-size:24px;font-weight:900;margin-bottom:6px"><?= e($resident['full_name']) ?></h2>
            <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:14px;opacity:.9">
                <span>🏠 Room <?= e($resident['room_number']) ?></span>
                <?php if ($resident['gender']): ?>
                <span>⚥ <?= e($resident['gender']) ?></span>
                <?php endif; ?>
                <?php if ($resident['date_of_birth']): ?>
                <span>🎂 <?= calcAge($resident['date_of_birth']) ?> (<?= fmtDate($resident['date_of_birth']) ?>)</span>
                <?php endif; ?>
                <?php if ($resident['admission_date']): ?>
                <span>📅 Admitted <?= fmtDate($resident['admission_date']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?= statusBadge($resident['status']) ?>
            <?php if ($checkedInNow): ?>
            <span class="badge badge-success">Has Visitor Now</span>
            <?php endif; ?>
            <a href="../residents/edit.php?id=<?= $resident['id'] ?>" class="btn btn-sm btn-accent">✏️ Edit</a>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:340px 1fr;gap:22px;align-items:start">

<!-- Left: Info Panel -->
<div style="display:flex;flex-direction:column;gap:16px">

    <!-- Stats Mini Cards -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
        <div class="card" style="padding:14px;text-align:center">
            <div style="font-size:24px;font-weight:900;color:var(--primary)"><?= $totalVisits ?></div>
            <div style="font-size:11px;color:var(--text-muted);font-weight:700;text-transform:uppercase">Total Visits</div>
        </div>
        <div class="card" style="padding:14px;text-align:center">
            <div style="font-size:24px;font-weight:900;color:var(--success)"><?= $checkedInNow ?></div>
            <div style="font-size:11px;color:var(--text-muted);font-weight:700;text-transform:uppercase">Now Inside</div>
        </div>
        <div class="card" style="padding:14px;text-align:center">
            <div style="font-size:24px;font-weight:900;color:var(--accent)"><?= $avgDuration ?>m</div>
            <div style="font-size:11px;color:var(--text-muted);font-weight:700;text-transform:uppercase">Avg Duration</div>
        </div>
    </div>

    <!-- Emergency Contact -->
    <div class="card">
        <div class="card-header"><div class="card-title">🚨 Emergency Contact</div></div>
        <div class="card-body">
            <?php if ($resident['emergency_contact_name']): ?>
            <p style="font-weight:700;font-size:15px;margin-bottom:6px"><?= e($resident['emergency_contact_name']) ?></p>
            <?php if ($resident['emergency_contact_relation']): ?>
            <p style="color:var(--text-muted);font-size:13px;margin-bottom:8px"><?= e($resident['emergency_contact_relation']) ?></p>
            <?php endif; ?>
            <?php if ($resident['emergency_contact_phone']): ?>
            <p>📞 <a href="tel:<?= e($resident['emergency_contact_phone']) ?>"><?= e($resident['emergency_contact_phone']) ?></a></p>
            <?php endif; ?>
            <?php else: ?>
            <p style="color:var(--text-muted)">No emergency contact recorded.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Medical Notes -->
    <div class="card">
        <div class="card-header"><div class="card-title">🏥 Medical Notes</div></div>
        <div class="card-body">
            <?php if ($resident['medical_notes']): ?>
            <p style="font-size:14px;line-height:1.6"><?= nl2br(e($resident['medical_notes'])) ?></p>
            <?php else: ?>
            <p style="color:var(--text-muted)">No medical notes on file.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($resident['status'] === 'Inactive' && !empty($resident['deactivation_reason'])): ?>
    <!-- Deactivation Reason -->
    <div class="card" style="border:1px solid var(--danger);">
        <div class="card-header" style="background-color:#ffeaea;"><div class="card-title" style="color:var(--danger);">⛔ Deactivation Reason</div></div>
        <div class="card-body">
            <p style="font-size:14px;line-height:1.6;color:var(--danger);"><?= nl2br(e($resident['deactivation_reason'])) ?></p>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Right: Visit History -->
<div class="card">
    <div class="card-header">
        <div class="card-title">📋 Visit History</div>
        <a href="../visitors/checkin.php" class="btn btn-sm btn-success">✅ Log New Visit</a>
    </div>
    <?php if ($visits): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Visitor</th>
                    <th>Relationship / Purpose</th>
                    <th>Check In</th>
                    <th>Duration</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($visits as $v): ?>
            <tr>
                <td>
                    <div class="td-name"><?= e($v['visitor_name']) ?></div>
                    <?php if ($v['contact_phone']): ?>
                    <div class="td-sub">📞 <?= e($v['contact_phone']) ?></div>
                    <?php endif; ?>
                    <?php if ($v['companion_details']): ?>
                    <div class="td-sub" style="font-size:11px; margin-top:2px; color:var(--primary)">
                        👥 Companions: <?= e($v['companion_details']) ?>
                    </div>
                    <?php endif; ?>                </td>
                <td style="font-size:13px">
                    <?= e($v['relationship'] ?: '—') ?>
                    <?php if ($v['purpose']): ?>
                    <div class="td-sub"><?= e($v['purpose']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;white-space:nowrap"><?= fmtDateTime($v['check_in_time']) ?></td>
                <td><?= fmtDuration($v['duration_minutes']) ?></td>
                <td><?= statusBadge($v['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">📋</div>
        <h3>No visits recorded yet</h3>
        <p>This resident hasn't had any visitors logged yet.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Resident Activity Log -->
<div class="card" style="margin-top: 22px;">
    <div class="card-header">
        <div class="card-title">📑 Resident Activity Log</div>
    </div>
    <?php if ($residentLogs): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($residentLogs as $log): ?>
            <tr>
                <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;"><?= fmtDateTime($log['created_at']) ?></td>
                <td>
                    <?php if ($log['user_name']): ?>
                        <div style="font-weight:700;"><?= e($log['user_name']) ?></div>
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
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">📑</div>
        <h3>No activity logs yet</h3>
        <p>There are no recorded system actions for this resident.</p>
    </div>
    <?php endif; ?>
</div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
