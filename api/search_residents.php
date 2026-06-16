<?php
/**
 * api/search_residents.php – Resident autocomplete search
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
$q  = trim($_GET['q'] ?? '');

if (strlen($q) < 1) {
    // Return all active residents if no query
    $stmt = $db->query("SELECT id, full_name, room_number, gender FROM residents WHERE status='Active' ORDER BY full_name LIMIT 30");
    echo json_encode($stmt->fetchAll());
    exit;
}

$stmt = $db->prepare("
    SELECT id, full_name, room_number, gender
    FROM residents
    WHERE status = 'Active' AND (full_name LIKE ? OR room_number LIKE ?)
    ORDER BY full_name
    LIMIT 15
");
$like = '%' . $q . '%';
$stmt->execute([$like, $like]);
echo json_encode($stmt->fetchAll());
