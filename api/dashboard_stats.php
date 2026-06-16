<?php
/**
 * api/dashboard_stats.php – Real-time stats for dashboard auto-refresh
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDB();

echo json_encode([
    'today'     => (int)$db->query("SELECT COUNT(*) FROM visit_logs WHERE DATE(check_in_time)=CURDATE()")->fetchColumn(),
    'checkedin' => (int)$db->query("SELECT COUNT(*) FROM visit_logs WHERE status='Checked In'")->fetchColumn(),
    'residents' => (int)$db->query("SELECT COUNT(*) FROM residents WHERE status='Active'")->fetchColumn(),
    'monthly'   => (int)$db->query("SELECT COUNT(*) FROM visit_logs WHERE MONTH(check_in_time)=MONTH(CURDATE()) AND YEAR(check_in_time)=YEAR(CURDATE())")->fetchColumn(),
]);
