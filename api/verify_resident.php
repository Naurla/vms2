<?php
/**
 * api/verify_resident.php – Secure resident name check endpoint for kiosk
 * Accepts name query, searches active records, handles multiple matches, enforces min character length.
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$db = getDB();
$name = trim($_GET['name'] ?? '');

if (strlen($name) < 3) {
    echo json_encode([
        'exists' => false, 
        'message' => 'Please enter at least 3 characters to search.'
    ]);
    exit;
}

try {
    // Perform partial match (wildcard) search on active residents
    $stmt = $db->prepare("
        SELECT id, full_name, room_number
        FROM residents
        WHERE status = 'Active' AND full_name LIKE ?
        ORDER BY full_name
        LIMIT 15
    ");
    $stmt->execute(['%' . $name . '%']);
    $matches = $stmt->fetchAll();

    if (count($matches) === 0) {
        echo json_encode([
            'exists' => false,
            'message' => 'No active resident found matching "' . htmlspecialchars($name) . '".'
        ]);
    } elseif (count($matches) === 1) {
        echo json_encode([
            'exists' => true,
            'match_type' => 'single',
            'id' => (int)$matches[0]['id'],
            'full_name' => $matches[0]['full_name'],
            'room_number' => $matches[0]['room_number']
        ]);
    } else {
        echo json_encode([
            'exists' => true,
            'match_type' => 'multiple',
            'matches' => array_map(function($m) {
                return [
                    'id' => (int)$m['id'],
                    'full_name' => $m['full_name'],
                    'room_number' => $m['room_number']
                ];
            }, $matches)
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error.']);
}
