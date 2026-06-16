<?php
/**
 * api/search_visitors.php – Visitor autocomplete search
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
$id = (int)($_GET['id'] ?? 0);

if ($id) {
    // Fetch single visitor by ID
    $stmt = $db->prepare("SELECT id, full_name, id_type, id_number, contact_phone, address FROM visitors WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $db->prepare("
    SELECT id, full_name, id_type, id_number, contact_phone
    FROM visitors
    WHERE full_name LIKE ? OR id_number LIKE ? OR contact_phone LIKE ?
    ORDER BY full_name
    LIMIT 10
");
$like = '%' . $q . '%';
$stmt->execute([$like, $like, $like]);
echo json_encode($stmt->fetchAll());
