<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';

$db = getDB();

try {
    $db->exec("ALTER TABLE residents ADD COLUMN deactivation_reason TEXT NULL AFTER status");
    echo "Added to residents.\n";
} catch (Exception $e) {
    echo "Residents: " . $e->getMessage() . "\n";
}

try {
    $db->exec("ALTER TABLE users ADD COLUMN deactivation_reason TEXT NULL AFTER is_active");
    echo "Added to users.\n";
} catch (Exception $e) {
    echo "Users: " . $e->getMessage() . "\n";
}
