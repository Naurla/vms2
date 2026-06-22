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
                    <th style="width: 80px;">ID</th>
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
            <?php foreach ($residents as $index => $res): ?>
            <tr>
                <td style="font-family: monospace; font-weight: bold; color: var(--text-secondary); font-size: 13px;">
                    <?= $index + 1 ?>
                </td>
                <td>
                    <div class="resident-profile">
                        <div class="resident-avatar">
                            <?= strtoupper(substr($res['full_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="resident-name">
                                <?= e($res['full_name']) ?>
                            </div>
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
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;max-width:220px;margin:0 auto">
                        <a href="../residents/view.php?id=<?= $res['id'] ?>" class="btn btn-sm btn-outline" style="justify-content:center;width:100%">👁 View</a>
                        <a href="../residents/edit.php?id=<?= $res['id'] ?>" class="btn btn-sm btn-secondary" title="Edit" style="justify-content:center;width:100%">✏️ Edit</a>
                        
                        <?php if ($res['status'] === 'Active'): ?>
                        <button type="button" class="btn btn-sm btn-danger" 
                                onclick="confirmStatusToggle(<?= $res['id'] ?>, '<?= e(addslashes($res['full_name'])) ?>', 'Active')"
                                title="Deactivate" style="grid-column:1/-1;justify-content:center;width:100%">
                            ⛔ Deactivate
                        </button>
                        <?php else: ?>
                        <button type="button" class="btn btn-sm btn-success" 
                                onclick="confirmStatusToggle(<?= $res['id'] ?>, '<?= e(addslashes($res['full_name'])) ?>', 'Inactive')"
                                title="Activate" style="justify-content:center;width:100%">
                            ✅ Activate
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" 
                                onclick="confirmRemoveResident(<?= $res['id'] ?>, '<?= e(addslashes($res['full_name'])) ?>', <?= (int)$res['total_visits'] ?>)"
                                title="Remove Resident" style="justify-content:center;width:100%">
                            🗑️ Remove
                        </button>
                        <?php endif; ?>
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

<!-- Status Toggle Confirmation Modal -->
<div class="modal-overlay" id="status-modal">
    <div class="modal">
        <div class="modal-icon" id="status-modal-icon">🔄</div>
        <h2 id="status-modal-title">Change Status?</h2>
        <p class="modal-sub" id="status-modal-message">Are you sure you want to change this resident's status?</p>
        <form method="POST" id="status-form">
            <?= csrfField() ?>
            <input type="hidden" name="toggle_id" id="status-toggle-id" value="">
            <input type="hidden" name="new_status" id="status-new-status" value="">
            <div class="modal-btns">
                <button type="button" class="btn-cancel" onclick="closeModal('status-modal')">Cancel</button>
                <button type="submit" class="btn-confirm" id="status-confirm-btn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- Remove Resident Confirmation Modal -->
<div class="modal-overlay" id="remove-modal">
    <div class="modal">
        <div class="modal-icon" id="remove-modal-icon">⚠️</div>
        <h2>Remove Resident?</h2>
        <p class="modal-sub" id="remove-modal-message">Are you sure you want to remove this resident?</p>
        <form method="POST" id="remove-form">
            <?= csrfField() ?>
            <input type="hidden" name="delete_id" id="remove-delete-id" value="">
            <div class="modal-btns">
                <button type="button" class="btn-cancel" onclick="closeModal('remove-modal')">Cancel</button>
                <button type="submit" class="btn-confirm btn-danger-confirm" id="remove-confirm-btn">Remove</button>
            </div>
        </form>
    </div>
</div>

<script>
initTableSearch('search-residents', 'residents-table');

function confirmStatusToggle(id, name, currentStatus) {
    const nextStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
    document.getElementById('status-toggle-id').value = id;
    document.getElementById('status-new-status').value = nextStatus;
    
    document.getElementById('status-modal-title').textContent = nextStatus === 'Inactive' ? 'Deactivate Resident?' : 'Activate Resident?';
    document.getElementById('status-modal-message').innerHTML = `Are you sure you want to set <strong>${name}</strong> to <strong>${nextStatus}</strong>?`;
    document.getElementById('status-modal-icon').textContent = nextStatus === 'Inactive' ? '⛔' : '✅';
    
    const confirmBtn = document.getElementById('status-confirm-btn');
    if (nextStatus === 'Inactive') {
        confirmBtn.className = 'btn-confirm btn-danger-confirm';
        confirmBtn.textContent = 'Deactivate';
    } else {
        confirmBtn.className = 'btn-confirm';
        confirmBtn.textContent = 'Activate';
    }
    
    openModal('status-modal');
}

function confirmRemoveResident(id, name, totalVisits) {
    document.getElementById('remove-delete-id').value = id;
    const confirmBtn = document.getElementById('remove-confirm-btn');
    
    if (totalVisits > 0) {
        document.getElementById('remove-modal-message').innerHTML = `<strong>${name}</strong> has <strong>${totalVisits} visit record(s)</strong> and cannot be permanently deleted. Please keep them as Inactive instead.`;
        confirmBtn.style.display = 'none';
        document.getElementById('remove-modal-icon').textContent = 'ℹ️';
    } else {
        document.getElementById('remove-modal-message').innerHTML = `Are you sure you want to permanently remove <strong>${name}</strong> from the system? This action cannot be undone.`;
        confirmBtn.style.display = 'block';
        document.getElementById('remove-modal-icon').textContent = '⚠️';
    }
    
    openModal('remove-modal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
