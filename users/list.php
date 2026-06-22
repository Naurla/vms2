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
    $uid    = (int)$_POST['toggle_user'];
    if ($uid == $self['id']) { setFlash('warning', 'You cannot deactivate your own account.'); redirect('../users/list.php'); }
    $cur    = $db->prepare("SELECT is_active FROM users WHERE id = ?");
    $cur->execute([$uid]);
    $cur    = $cur->fetchColumn();
    $newVal = $cur ? 0 : 1;
    $db->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$newVal, $uid]);
    setFlash('success', 'User status updated.');
    redirect('../users/list.php');
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    verifyCsrf();
    $uid = (int)$_POST['delete_user'];
    if ($uid == $self['id']) { setFlash('warning', 'You cannot delete your own account.'); redirect('../users/list.php'); }
    $db->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
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
                        : '<span class="badge badge-danger">Inactive</span>' ?>
                </td>
                <td style="font-size:12px;color:var(--text-muted)">
                    <?= $u['last_login'] ? fmtDateTime($u['last_login']) : 'Never' ?>
                </td>
                <td style="font-size:12px;color:var(--text-muted)"><?= fmtDate($u['created_at']) ?></td>
                <td style="text-align:center">
                    <div style="display:flex;gap:6px;justify-content:center">
                        <a href="../users/edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-secondary">✏️ Edit</a>
                        <?php if ($u['id'] != $self['id']): ?>
                        <form method="POST" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="toggle_user" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                                    data-confirm="<?= $u['is_active'] ? 'Deactivate this user?' : 'Activate this user?' ?>"
                                    style="<?= !$u['is_active'] ? '' : '' ?>">
                                <?= $u['is_active'] ? '⛔' : '✅' ?>
                            </button>
                        </form>
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

<script>
initTableSearch('search-users', 'users-table');
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
