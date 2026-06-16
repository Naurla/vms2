<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Checks ────────────────────────────────────────────────────

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// ── Guards ────────────────────────────────────────────────────

function requireAuth(): void
{
    if (!isLoggedIn()) {
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        header('Location: ' . $base . 'index.php?msg=login_required');
        exit;
    }
}

function requireAdmin(): void
{
    requireAuth();
    if (!isAdmin()) {
        setFlash('error', 'Access denied. Administrators only.');
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        header('Location: ' . $base . 'dashboard.php');
        exit;
    }
}

// ── Current user ──────────────────────────────────────────────

function currentUser(): array
{
    return [
        'id'        => $_SESSION['user_id']        ?? null,
        'username'  => $_SESSION['user_username']  ?? '',
        'full_name' => $_SESSION['user_full_name'] ?? '',
        'role'      => $_SESSION['user_role']      ?? '',
    ];
}

// ── Flash messages ────────────────────────────────────────────

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ── CSRF ──────────────────────────────────────────────────────

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
    }
}
