<?php

/**
 * Escape a value for safe HTML output.
 */
function e(mixed $val): string
{
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

/**
 * Format a date string for display.
 */
function fmtDate(?string $date, string $format = 'M d, Y'): string
{
    if (!$date) return '—';
    return date($format, strtotime($date));
}

/**
 * Format a datetime string for display.
 */
function fmtDateTime(?string $dt): string
{
    if (!$dt) return '—';
    return date('M d, Y h:i A', strtotime($dt));
}

/**
 * Format duration in minutes to a human-readable string.
 */
function fmtDuration(?int $minutes): string
{
    if ($minutes === null || $minutes < 0) return '—';
    if ($minutes < 60) return $minutes . ' min';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h . 'h' . ($m > 0 ? ' ' . $m . 'm' : '');
}

/**
 * Calculate age from date of birth.
 */
function calcAge(?string $dob): string
{
    if (!$dob) return '—';
    return (new DateTime($dob))->diff(new DateTime())->y . ' yrs';
}

/**
 * Return an HTML badge for a status value.
 */
function statusBadge(string $status): string
{
    $map = [
        'Checked In'   => 'success',
        'Checked Out'  => 'secondary',
        'Active'       => 'success',
        'Inactive'     => 'danger',
        'admin'        => 'primary',
        'receptionist' => 'info',
    ];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge badge-' . $cls . '">' . e($status) . '</span>';
}

/**
 * Safely get a POST value.
 */
function post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

/**
 * Safely get a GET value.
 */
function get(string $key, string $default = ''): string
{
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

/**
 * Redirect and exit.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Pluralize a word.
 */
function plural(int $count, string $singular, string $plural = ''): string
{
    $p = $plural ?: $singular . 's';
    return $count . ' ' . ($count === 1 ? $singular : $p);
}

/**
 * Return today's visit count from DB.
 */
function todayVisitCount(\PDO $db): int
{
    $stmt = $db->query("SELECT COUNT(*) FROM visit_logs WHERE DATE(check_in_time) = CURDATE()");
    return (int)$stmt->fetchColumn();
}

/**
 * Return currently checked-in visitor count.
 */
function checkedInCount(\PDO $db): int
{
    $stmt = $db->query("SELECT COUNT(*) FROM visit_logs WHERE status = 'Checked In'");
    return (int)$stmt->fetchColumn();
}

/**
 * Generate a unique random visit code.
 */
function generateVisitCode(\PDO $db): string
{
    do {
        $code = 'VMS-' . strtoupper(bin2hex(random_bytes(2)));
        $stmt = $db->prepare("SELECT COUNT(*) FROM visit_logs WHERE visit_code = ?");
        $stmt->execute([$code]);
        $exists = $stmt->fetchColumn() > 0;
    } while ($exists);
    return $code;
}

/**
 * Log a system activity.
 */
function logActivity(\PDO $db, ?int $userId, string $action, string $details = '', ?int $residentId = null): void
{
    try {
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, resident_id, action, details, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $residentId, $action, $details]);
    } catch (\Exception $e) {
        // Silently fail if logging fails so it doesn't break the main application flow
        error_log("Activity Log Error: " . $e->getMessage());
    }
}
