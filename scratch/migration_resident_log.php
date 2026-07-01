<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';

$db = getDB();

try {
    $db->exec("ALTER TABLE activity_logs ADD COLUMN resident_id INT UNSIGNED NULL AFTER user_id");
    echo "Added resident_id to activity_logs.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
