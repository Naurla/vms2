<?php
/**
 * visitors/list.php – Visit Log (Admin only)
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
requireAdmin();
$db = getDB();

// Filters
$filterStatus   = get('status');
$filterDate     = get('date');
$filterResident = (int)get('resident_id');

$where  = ['1=1'];
$params = [];

if ($filterStatus)   { $where[] = 'vl.status = ?';              $params[] = $filterStatus; }
if ($filterDate)     { $where[] = 'DATE(vl.check_in_time) = ?'; $params[] = $filterDate;   }
if ($filterResident) { $where[] = 'vl.resident_id = ?';         $params[] = $filterResident; }

$whereStr = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT vl.*, v.full_name AS visitor_name, v.id_type, v.id_number, v.contact_phone,
           r.full_name AS resident_name, r.room_number,
           u.full_name AS staff_checkin,
           u2.full_name AS staff_checkout
    FROM visit_logs vl
    JOIN visitors v  ON v.id = vl.visitor_id
    JOIN residents r ON r.id = vl.resident_id
    JOIN users u     ON u.id = vl.checked_in_by
    LEFT JOIN users u2 ON u2.id = vl.checked_out_by
    WHERE $whereStr
    ORDER BY vl.check_in_time DESC
    LIMIT 500
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// For resident filter dropdown
$allResidents = $db->query("SELECT id, full_name, room_number FROM residents ORDER BY full_name")->fetchAll();

$pageTitle   = 'Visit Log';
$activeNav   = 'visit-log';
$breadcrumbs = [['label' => 'Visitors'], ['label' => 'Visit Log']];
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Filters -->
<div class="card mb-20">
    <div class="card-body" style="padding:16px 22px">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="min-width:160px">
                <label>Status</label>
                <select name="status" class="filter-select">
                    <option value="">All Statuses</option>
                    <option value="Checked In"  <?= $filterStatus === 'Checked In'  ? 'selected' : '' ?>>Checked In</option>
                    <option value="Checked Out" <?= $filterStatus === 'Checked Out' ? 'selected' : '' ?>>Checked Out</option>
                </select>
            </div>
            <div class="form-group" style="min-width:180px">
                <label>Date</label>
                <input type="date" name="date" value="<?= e($filterDate) ?>">
            </div>
            <div class="form-group" style="min-width:220px">
                <label>Resident</label>
                <select name="resident_id" class="filter-select">
                    <option value="">All Residents</option>
                    <?php foreach ($allResidents as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= $filterResident == $r['id'] ? 'selected' : '' ?>>
                        <?= e($r['full_name']) ?> (Rm <?= e($r['room_number']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:1px">
                <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
                <a href="list.php" class="btn btn-secondary btn-sm">✕ Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="page-actions">
    <div class="page-actions-left">
        <strong style="color:var(--text-secondary)"><?= plural(count($logs), 'record') ?> found</strong>
    </div>
    <div class="page-actions-right">
        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" id="search-log" placeholder="Search log…">
        </div>
        <a href="../visitors/checkin.php" class="btn btn-success btn-sm">✅ New Check In</a>
    </div>
</div>

<div class="card">
    <?php if ($logs): ?>
    <div class="table-wrapper">
        <table id="visit-log-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Visitor</th>
                    <th>ID</th>
                    <th>Resident / Room</th>
                    <th>Purpose</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Duration</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $i => $log): ?>
            <tr>
                <td style="color:var(--text-muted);font-size:12px"><?= $i + 1 ?></td>
                <td>
                    <div class="td-name"><?= e($log['visitor_name']) ?></div>
                    <?php if ($log['contact_phone']): ?>
                    <div class="td-sub">📞 <?= e($log['contact_phone']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--text-muted)">
                    <?= e($log['id_type']) ?><br><?= e($log['id_number']) ?>
                </td>
                <td>
                    <div class="td-name"><?= e($log['resident_name']) ?></div>
                    <div class="td-sub">Room <?= e($log['room_number']) ?></div>
                </td>
                <td style="font-size:13px">
                    <?= e($log['purpose'] ?: '—') ?>
                    <?php if ($log['relationship']): ?>
                    <div class="td-sub"><?= e($log['relationship']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;white-space:nowrap"><?= fmtDateTime($log['check_in_time']) ?></td>
                <td style="font-size:12px;white-space:nowrap"><?= fmtDateTime($log['check_out_time']) ?></td>
                <td><?= fmtDuration($log['duration_minutes']) ?></td>
                <td><?= statusBadge($log['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">📋</div>
        <h3>No visit records found</h3>
        <p>Try adjusting your filters or <a href="../visitors/checkin.php">check in a visitor</a>.</p>
    </div>
    <?php endif; ?>
</div>

<script>
initTableSearch('search-log', 'visit-log-table');
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
