<?php
/**
 * users/add.php – Add New User (Admin only)
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
requireAdmin();
$db = getDB();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $fullName = post('full_name');
    $username = post('username');
    $password = post('password');
    $confirm  = post('confirm_password');
    $role     = post('role', 'receptionist');

    if (!$fullName) $errors[] = 'Full name is required.';
    if (!$username) $errors[] = 'Username is required.';
    if (!$password) $errors[] = 'Password is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['admin', 'receptionist'])) $errors[] = 'Invalid role.';

    if (!$errors) {
        $dup = $db->prepare("SELECT id FROM users WHERE username = ?");
        $dup->execute([$username]);
        if ($dup->fetchColumn()) $errors[] = "Username \"{$username}\" is already taken.";
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?,?,?,?)")
           ->execute([$username, $hash, $fullName, $role]);
        setFlash('success', "User \"{$fullName}\" created successfully.");
        redirect('../users/list.php');
    }
}

$pageTitle   = 'Add User';
$activeNav   = 'users';
$breadcrumbs = [['label' => 'Users', 'url' => '../users/list.php'], ['label' => 'Add User']];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:600px">
    <div class="card-header">
        <div class="card-title">👤 Add New Staff User</div>
    </div>
    <div class="card-body">

        <?php if ($errors): ?>
        <div class="alert alert-danger" data-auto-dismiss>
            <span class="alert-icon">❌</span>
            <div><?= implode('<br>', array_map('e', $errors)) ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" id="add-user-form" novalidate>
            <?= csrfField() ?>

            <div class="form-grid mb-20">
                <div class="form-group full">
                    <label for="full_name">Full Name <span class="required-star">*</span></label>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= e(post('full_name')) ?>"
                           placeholder="e.g. Juan dela Cruz" required autofocus>
                </div>
                <div class="form-group">
                    <label for="username">Username <span class="required-star">*</span></label>
                    <input type="text" id="username" name="username"
                           value="<?= e(post('username')) ?>"
                           placeholder="e.g. jdelacruz"
                           autocomplete="username" required>
                </div>
                <div class="form-group">
                    <label for="role">Role <span class="required-star">*</span></label>
                    <select id="role" name="role" required>
                        <option value="receptionist" <?= post('role', 'receptionist') === 'receptionist' ? 'selected' : '' ?>>Receptionist</option>
                        <option value="admin"        <?= post('role') === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="password">Password <span class="required-star">*</span></label>
                    <div class="password-toggle">
                        <input type="password" id="password" name="password"
                               placeholder="Min. 6 characters"
                               autocomplete="new-password" required>
                        <button type="button" class="toggle-eye">👁</button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required-star">*</span></label>
                    <div class="password-toggle">
                        <input type="password" id="confirm_password" name="confirm_password"
                               placeholder="Repeat password"
                               autocomplete="new-password" required>
                        <button type="button" class="toggle-eye">👁</button>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:12px">
                <button type="submit" class="btn btn-primary" id="submit-btn">💾 Create User</button>
                <a href="../users/list.php" class="btn btn-secondary">✕ Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('add-user-form').addEventListener('submit', function() {
    document.getElementById('submit-btn').innerHTML = '<span class="spinner"></span> Creating…';
    document.getElementById('submit-btn').disabled = true;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
