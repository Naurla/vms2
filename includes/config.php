<?php
/**
 * Application Configuration
 * VMS – Home for the Aged
 *
 * ⚠️  Edit DB_USER and DB_PASS to match your MySQL setup.
 */

// ── Database ─────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'vms_home_aged');
define('DB_USER',    'root');       // ← change if needed
define('DB_PASS',    '');           // ← change if needed
define('DB_CHARSET', 'utf8mb4');

// ── Application ───────────────────────────────────────────────
define('APP_NAME',    'Care Home VMS');
define('APP_TAGLINE', 'Home for the Aged – Visitor Management System');
define('APP_VERSION', '1.0.0');

// ── Timezone ─────────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

// ── Session hardening ─────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
}
