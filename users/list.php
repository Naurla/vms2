<?php
/**
 * users/list.php – Manage Users (Admin only)
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
requireAdmin();
$db   = getDB();
$self = currentUser();

// Handle toggle active
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user'])) {
    verifyCsrf();
    $uid    = (int)$_POST['toggle_id'];
    if ($uid == $self['id']) { setFlash('warning', 'You cannot deactivate your own account.'); redirect('../users/list.php'); }
    $cur    = $db->prepare("SELECT is_active FROM users WHERE id = ?");
    $cur->execute([$uid]);
    $isActive = $cur->fetchColumn();
    $newVal = $isActive ? 0 : 1;
    
    if ($newVal === 0) {
        $deactivationReason = trim($_POST['deactivation_reason'] ?? '');
        if ($deactivationReason === '') {
            setFlash('warning', 'A reason is required when deactivating a user.');
            redirect('../users/list.php');
        }
        $db->prepare("UPDATE users SET is_active=?, deactivation_reason=? WHERE id=?")->execute([$newVal, $deactivationReason, $uid]);
        
        $uName = $db->prepare("SELECT full_name FROM users WHERE id=?");
        $uName->execute([$uid]);
        logActivity($db, $self['id'], 'Deactivated User', "Name: " . $uName->fetchColumn() . ", Reason: {$deactivationReason}");
    } else {
        $db->prepare("UPDATE users SET is_active=?, deactivation_reason=NULL WHERE id=?")->execute([$newVal, $uid]);
        
        $uName = $db->prepare("SELECT full_name FROM users WHERE id=?");
        $uName->execute([$uid]);
        logActivity($db, $self['id'], 'Activated User', "Name: " . $uName->fetchColumn());
    }
    
    setFlash('success', 'User status updated.');
    redirect('../users/list.php');
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    verifyCsrf();
    $uid = (int)$_POST['delete_user'];
    if ($uid == $self['id']) { setFlash('warning', 'You cannot delete your own account.'); redirect('../users/list.php'); }
    
    $uNameStmt = $db->prepare("SELECT full_name FROM users WHERE id=?");
    $uNameStmt->execute([$uid]);
    $uName = $uNameStmt->fetchColumn();

    $db->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
    
    logActivity($db, $self['id'], 'Removed User', "Name: {$uName}");
    
    setFlash('success', 'User deleted.');
    redirect('../users/list.php');
}

$users = $db->query("SELECT * FROM users ORDER BY role, full_name")->fetchAll();

$pageTitle   = 'Manage Users';
$activeNav   = 'users';
$breadcrumbs = [['label' => 'Admin'], ['label' => 'Users']];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-actions">
    <div class="page-actions-left">
        <strong style="color:var(--text-secondary)"><?= plural(count($users), 'user') ?></strong>
    </div>
    <div class="page-actions-right">
        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" id="search-users" placeholder="Search users…">
        </div>
        <a href="../users/add.php" class="btn btn-primary">➕ Add User</a>
    </div>
</div>

<div class="card">
    <div class="table-wrapper">
        <table id="users-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Created</th>
                    <th style="text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="user-avatar" style="width:36px;height:36px;font-size:13px;background:<?= $u['role'] === 'admin' ? 'linear-gradient(135deg,var(--primary),var(--primary-dark))' : 'linear-gradient(135deg,var(--accent-light),var(--accent))' ?>;color:<?= $u['role'] === 'admin' ? '#fff' : 'var(--accent-dark)' ?>">
                            <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="fw-700"><?= e($u['full_name']) ?></div>
                            <?php if ($u['id'] == $self['id']): ?>
                            <span style="font-size:11px;color:var(--primary);font-weight:700">(You)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td style="font-family:monospace;font-size:13px;color:var(--text-secondary)">
                    <?= e($u['email']) ?>
                </td>
                <td><?= statusBadge($u['role']) ?></td>
                <td>
                    <?= $u['is_active']
                        ? '<span class="badge badge-success">Active</span>'
                        : '<span class="badge badge-danger" title="Reason: ' . e($u['deactivation_reason']) . '">Inactive</span>' ?>
                </td>
                <td style="font-size:12px;color:var(--text-muted)">
                    <?= $u['last_login'] ? fmtDateTime($u['last_login']) : 'Never' ?>
                </td>
                <td style="font-size:12px;color:var(--text-muted)"><?= fmtDate($u['created_at']) ?></td>
                <td style="text-align:center">
                    <div style="display:flex;gap:6px;justify-content:center">
                        <a href="../users/edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-secondary">✏️ Edit</a>
                        <?php if ($u['id'] != $self['id']): ?>
                        <button type="button" class="btn btn-sm <?= $u['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                                onclick="confirmUserStatus(<?= $u['id'] ?>, '<?= e(addslashes($u['full_name'])) ?>', <?= $u['is_active'] ? 1 : 0 ?>)">
                            <?= $u['is_active'] ? '⛔' : '✅' ?>
                        </button>
                        <form method="POST" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="delete_user" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger"
                                    data-confirm="Permanently delete <?= e($u['full_name']) ?>?">🗑️</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="status-modal">
    <div class="modal">
        <div class="modal-icon" id="status-modal-icon">🔄</div>
        <h2 id="status-modal-title">Change Status?</h2>
        <p class="modal-sub" id="status-modal-message">Are you sure you want to change this user's status?</p>
        <form method="POST" id="status-form">
            <?= csrfField() ?>
            <input type="hidden" name="toggle_user" value="1">
            <input type="hidden" name="toggle_id" id="status-toggle-id" value="">
            
            <div id="status-reason-wrapper" style="display:none; text-align:left; margin-bottom:15px; width:100%;">
                <label for="status_deactivation_reason" style="font-size:12px; font-weight:bold; color:var(--text-secondary); display:block; margin-bottom:4px;">Reason for Deactivation <span class="required-star">*</span></label>
                <textarea id="status_deactivation_reason" name="deactivation_reason" rows="3" style="width:100%; border:1px solid #e2eaf0; border-radius:var(--radius-sm); padding:8px;" placeholder="Explain why this user is being deactivated"></textarea>
            </div>
            
            <div class="modal-btns">
                <button type="button" class="btn-cancel" onclick="closeModal('status-modal')">Cancel</button>
                <button type="submit" class="btn-confirm" id="status-confirm-btn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
initTableSearch('search-users', 'users-table');

function confirmUserStatus(id, name, isActive) {
    document.getElementById('status-toggle-id').value = id;
    const nextStatus = isActive ? 'Inactive' : 'Active';
    
    document.getElementById('status-modal-title').textContent = nextStatus === 'Inactive' ? 'Deactivate User?' : 'Activate User?';
    document.getElementById('status-modal-message').innerHTML = `Are you sure you want to set <strong>${name}</strong> to <strong>${nextStatus}</strong>?`;
    document.getElementById('status-modal-icon').textContent = nextStatus === 'Inactive' ? '⛔' : '✅';
    
    const confirmBtn = document.getElementById('status-confirm-btn');
    const reasonWrapper = document.getElementById('status-reason-wrapper');
    const reasonInput = document.getElementById('status_deactivation_reason');
    
    if (nextStatus === 'Inactive') {
        confirmBtn.className = 'btn-confirm btn-danger-confirm';
        confirmBtn.textContent = 'Deactivate';
        reasonWrapper.style.display = 'block';
        reasonInput.required = true;
        reasonInput.value = '';
    } else {
        confirmBtn.className = 'btn-confirm';
        confirmBtn.textContent = 'Activate';
        reasonWrapper.style.display = 'none';
        reasonInput.required = false;
        reasonInput.value = '';
    }
    
    openModal('status-modal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
