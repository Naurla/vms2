<?php
/**
 * users/edit.php – Edit User / Change Password
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
$db   = getDB();
$self = currentUser();

$id = (int)get('id', $self['id']);
// Only admins can edit other users
if ($id != $self['id'] && !isAdmin()) {
    setFlash('error', 'Access denied.');
    redirect('../dashboard.php');
}

$user = $db->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$id]);
$user = $user->fetch();
if (!$user) { setFlash('error', 'User not found.'); redirect('../users/list.php'); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $fullName   = post('full_name');
    $email      = post('email');
    $role       = post('role', $user['role']);
    $newPass    = post('new_password');
    $confirmPass= post('confirm_password');

    if (!$fullName) $errors[] = 'Full name is required.';
    if (!$email) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!$errors) {
        $dup = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $dup->execute([$email, $id]);
        if ($dup->fetchColumn()) $errors[] = "Email \"{$email}\" is already taken.";
    }

    if ($newPass) {
        if (strlen($newPass) < 6) $errors[] = 'New password must be at least 6 characters.';
        if ($newPass !== $confirmPass) $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $hash = $newPass ? password_hash($newPass, PASSWORD_BCRYPT) : $user['password'];
        $roleToSet = isAdmin() ? $role : $user['role']; // Only admins can change role
        $db->prepare("UPDATE users SET full_name=?, email=?, password=?, role=? WHERE id=?")
           ->execute([$fullName, $email, $hash, $roleToSet, $id]);
        setFlash('success', 'Profile updated successfully.');
        redirect($id == $self['id'] ? '../dashboard.php' : '../users/list.php');
    }
}

$pageTitle   = 'Edit User';
$activeNav   = 'users';
$breadcrumbs = [['label' => 'Users', 'url' => '../users/list.php'], ['label' => 'Edit']];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:600px">
    <div class="card-header">
        <div class="card-title">✏️ Edit User – <?= e($user['full_name']) ?></div>
    </div>
    <div class="card-body">

        <?php if ($errors): ?>
        <div class="alert alert-danger" data-auto-dismiss>
            <span class="alert-icon">❌</span>
            <div><?= implode('<br>', array_map('e', $errors)) ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" id="edit-user-form" novalidate>
            <?= csrfField() ?>

            <p class="section-label">Profile</p>
            <div class="form-grid mb-20">
                <div class="form-group full">
                    <label for="full_name">Full Name <span class="required-star">*</span></label>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= e(post('full_name') ?: $user['full_name']) ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="email">Email Address <span class="required-star">*</span></label>
                    <input type="email" id="email" name="email"
                           value="<?= e(post('email') ?: $user['email']) ?>" required>
                </div>
                <?php if (isAdmin()): ?>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <option value="receptionist" <?= $user['role'] === 'receptionist' ? 'selected' : '' ?>>Receptionist</option>
                        <option value="admin"        <?= $user['role'] === 'admin'        ? 'selected' : '' ?>>Administrator</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <p class="section-label">Change Password <span style="font-size:12px;font-weight:400;color:var(--text-muted)">(leave blank to keep current password)</span></p>
            <div class="form-grid mb-24">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="password-toggle">
                        <input type="password" id="new_password" name="new_password" placeholder="Min. 6 characters">
                        <button type="button" class="toggle-eye">👁</button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-toggle">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password">
                        <button type="button" class="toggle-eye">👁</button>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:12px">
                <button type="submit" class="btn btn-accent" id="submit-btn">💾 Save Changes</button>
                <a href="<?= isAdmin() ? '../users/list.php' : '../dashboard.php' ?>" class="btn btn-secondary">✕ Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('edit-user-form').addEventListener('submit', function() {
    document.getElementById('submit-btn').innerHTML = '<span class="spinner"></span> Saving…';
    document.getElementById('submit-btn').disabled = true;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
