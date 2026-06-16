<?php
/**
 * residents/list.php – Residents List
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
$db = getDB();

$filterStatus = get('status', 'Active');

// Handle delete (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && isAdmin()) {
    verifyCsrf();
    $delId = (int)$_POST['delete_id'];
    // Check if resident has visits
    $count = $db->prepare("SELECT COUNT(*) FROM visit_logs WHERE resident_id = ?");
    $count->execute([$delId]);
    if ($count->fetchColumn() > 0) {
        setFlash('warning', 'Cannot delete: this resident has visit records. Set them to Inactive instead.');
    } else {
        $db->prepare("DELETE FROM residents WHERE id = ?")->execute([$delId]);
        setFlash('success', 'Resident removed successfully.');
    }
    redirect('../residents/list.php');
}

// Handle toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    verifyCsrf();
    $togId    = (int)$_POST['toggle_id'];
    $newStatus = $_POST['new_status'] ?? 'Active';
    $db->prepare("UPDATE residents SET status=? WHERE id=?")->execute([$newStatus, $togId]);
    setFlash('success', 'Resident status updated.');
    redirect('../residents/list.php');
}

$where  = $filterStatus ? "WHERE status = ?" : "WHERE 1=1";
$params = $filterStatus ? [$filterStatus] : [];

$stmt = $db->prepare("
    SELECT r.*,
           (SELECT COUNT(*) FROM visit_logs vl WHERE vl.resident_id = r.id) AS total_visits,
           (SELECT COUNT(*) FROM visit_logs vl WHERE vl.resident_id = r.id AND vl.status='Checked In') AS active_visits
    FROM residents r
    $where
    ORDER BY r.full_name
");
$stmt->execute($params);
$residents = $stmt->fetchAll();

$pageTitle   = 'Residents';
$activeNav   = 'residents';
$breadcrumbs = [['label' => 'Residents'], ['label' => 'All Residents']];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-actions">
    <div class="page-actions-left">
        <a href="?status=Active"   class="btn btn-sm <?= $filterStatus === 'Active'   ? 'btn-primary'   : 'btn-secondary' ?>">Active</a>
        <a href="?status=Inactive" class="btn btn-sm <?= $filterStatus === 'Inactive' ? 'btn-primary'   : 'btn-secondary' ?>">Inactive</a>
        <a href="?status="         class="btn btn-sm <?= !$filterStatus               ? 'btn-primary'   : 'btn-secondary' ?>">All</a>
    </div>
    <div class="page-actions-right">
        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" id="search-residents" placeholder="Search residents…">
        </div>
        <a href="../residents/add.php" class="btn btn-accent">➕ Add Resident</a>
    </div>
</div>

<div class="card">
    <?php if ($residents): ?>
    <div class="table-wrapper">
        <table id="residents-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Room</th>
                    <th>Age / Gender</th>
                    <th>Emergency Contact</th>
                    <th>Admitted</th>
                    <th>Visits</th>
                    <th>Status</th>
                    <th style="text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($residents as $res): ?>
            <tr>
                <td>
                    <div class="resident-profile">
                        <div class="resident-avatar">
                            <?= strtoupper(substr($res['full_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="resident-name"><?= e($res['full_name']) ?></div>
                            <?php if ($res['active_visits']): ?>
                            <span class="badge badge-success" style="font-size:10px">Has visitor now</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td><span class="badge badge-gold">Room <?= e($res['room_number']) ?></span></td>
                <td>
                    <?= calcAge($res['date_of_birth']) ?>
                    <?php if ($res['gender']): ?>
                    <div class="td-sub"><?= e($res['gender']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?= e($res['emergency_contact_name'] ?: '—') ?>
                    <?php if ($res['emergency_contact_phone']): ?>
                    <div class="td-sub">📞 <?= e($res['emergency_contact_phone']) ?></div>
                    <?php endif; ?>
                    <?php if ($res['emergency_contact_relation']): ?>
                    <div class="td-sub"><?= e($res['emergency_contact_relation']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px"><?= fmtDate($res['admission_date']) ?></td>
                <td>
                    <span class="badge badge-info"><?= $res['total_visits'] ?> visit<?= $res['total_visits'] != 1 ? 's' : '' ?></span>
                </td>
                <td><?= statusBadge($res['status']) ?></td>
                <td style="text-align:center">
                    <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
                        <a href="../residents/view.php?id=<?= $res['id'] ?>" class="btn btn-sm btn-outline">👁 View</a>
                        <a href="../residents/edit.php?id=<?= $res['id'] ?>" class="btn btn-sm btn-secondary">✏️</a>
                        <!-- Toggle status -->
                        <form method="POST" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="toggle_id" value="<?= $res['id'] ?>">
                            <input type="hidden" name="new_status" value="<?= $res['status'] === 'Active' ? 'Inactive' : 'Active' ?>">
                            <button type="submit" class="btn btn-sm <?= $res['status'] === 'Active' ? 'btn-danger' : 'btn-success' ?>"
                                    data-confirm="<?= $res['status'] === 'Active' ? 'Mark as Inactive?' : 'Mark as Active?' ?>">
                                <?= $res['status'] === 'Active' ? '⛔' : '✅' ?>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">👵</div>
        <h3>No residents found</h3>
        <p>Start by adding residents to the system.</p>
        <a href="../residents/add.php" class="btn btn-accent mt-16">➕ Add First Resident</a>
    </div>
    <?php endif; ?>
</div>

<script>
initTableSearch('search-residents', 'residents-table');
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
