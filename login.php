<?php
/**
 * login.php – Login Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in → dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = post('email');
    $password = post('password');

    if (!$email || !$password) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']        = $user['id'];
                $_SESSION['user_email']     = $user['email'];
                $_SESSION['user_full_name'] = $user['full_name'];
                $_SESSION['user_role']      = $user['role'];

                // Update last login
                $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                   ->execute([$user['id']]);

                redirect('dashboard.php');
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (Throwable $e) {
            $error = 'System error. Please ensure database setup has been run.';
        }
    }
}

$loginMsg = get('msg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Care Home VMS – Visitor Management System for Home for the Aged. Secure staff login portal.">
    <title>Login – Care Home VMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">

    <div class="login-card">

        <!-- Logo -->
        <div class="login-logo">
            <div class="logo-icon">🏠</div>
            <h1>Care Home VMS</h1>
            <p class="tagline">Home for the Aged · Visitor Management System</p>
        </div>

        <!-- Session expired notice -->
        <?php if ($loginMsg === 'login_required'): ?>
        <div class="alert alert-warning" data-auto-dismiss>
            <span class="alert-icon">⚠️</span>
            <span>Your session has expired. Please log in again.</span>
        </div>
        <?php endif; ?>

        <!-- Error -->
        <?php if ($error): ?>
        <div class="alert alert-danger" data-auto-dismiss>
            <span class="alert-icon">❌</span>
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" id="login-form" novalidate>
            <div class="form-group mb-16">
                <label for="email">Email Address</label>
                <input type="email"
                       id="email"
                       name="email"
                       value="<?= e(post('email')) ?>"
                       placeholder="Enter your email"
                       autocomplete="email"
                       required
                       autofocus>
            </div>

            <div class="form-group mb-20">
                <label for="password">Password</label>
                <div class="password-toggle">
                    <input type="password"
                           id="password"
                           name="password"
                           placeholder="Enter your password"
                           autocomplete="current-password"
                           required>
                    <button type="button" class="toggle-eye" title="Show/hide password">👁</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center" id="login-btn">
                🔐 Sign In
            </button>
        </form>

        <div class="login-footer">
            <p>Don't have an account? Contact your administrator.</p>
        </div>

    </div>
</div>

<script>
document.getElementById('login-form').addEventListener('submit', function() {
    const btn = document.getElementById('login-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Signing in…';
});

// Auto-dismiss alerts
document.querySelectorAll('.alert[data-auto-dismiss]').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity .4s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
    }, 5000);
});

// Password toggle
document.querySelector('.toggle-eye')?.addEventListener('click', function() {
    const inp = document.getElementById('password');
    if (inp.type === 'password') { inp.type = 'text'; this.textContent = '🙈'; }
    else                         { inp.type = 'password'; this.textContent = '👁'; }
});
</script>
</body>
</html>
